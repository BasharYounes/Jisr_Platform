<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Support\ProjectTemplateAuthorization;
use App\Models\ProjectAssignment;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateApplication;
use DomainException;
use Illuminate\Support\Facades\DB;

class AcceptProjectTemplateApplicationAction
{
    private const ACTIVE_MEMBER_STATUS = 'active';

    public function __construct(
        private readonly AssignProjectAction $assignProjectAction
    ) {}

    public function execute(
        ProjectTemplateApplication $application,
        int $supervisorId,
        array $data = []
    ): ProjectTemplateApplication {
        return DB::transaction(function () use ($application, $supervisorId, $data) {
            /*
             * نقفل الطلب نفسه حتى لا يمكن قبوله مرتين في نفس الوقت.
             */
            $application = ProjectTemplateApplication::query()
                ->with('student')
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * نقفل القالب حتى لا تنشئ عمليتا قبول متزامنتان فريقين مختلفين
             * لنفس Project Template.
             */
            $template = ProjectTemplate::query()
                ->with('tasks')
                ->whereKey($application->project_template_id)
                ->lockForUpdate()
                ->firstOrFail();

            ProjectTemplateAuthorization::ensureCreator($template, $supervisorId);

            $this->ensureApplicationIsPending($application);

            if ($application->project_assignment_id !== null) {
                throw new DomainException(
                    'يوجد تكليف سابق لهذا الطلب. | An assignment already exists for this application.'
                );
            }

            $assignment = $this->findOpenTeamAssignment(
                projectTemplateId: $template->id,
                supervisorId: $supervisorId
            );

            /*
             * أول طالب مقبول: ننشئ Assignment جديداً.
             */
            if ($assignment === null) {
                $assignment = $this->assignProjectAction->execute([
                    'project_template_id' => $template->id,
                    'students' => [
                        [
                            'student_id' => $application->student_user_id,
                        ],
                    ],
                ]);
            } else {
                /*
                 * يوجد فريق مفتوح: نضيف الطالب الجديد إليه.
                 */
                $this->ensureTeamCanAcceptStudent(
                    assignment: $assignment,
                    template: $template,
                    studentId: $application->student_user_id
                );

                $assignment->members()->create([
                    'student_id' => $application->student_user_id,
                    'role' => null,
                    'status' => self::ACTIVE_MEMBER_STATUS,
                ]);
            }

            $application->update([
                'status' => ProjectTemplateApplicationStatus::ACCEPTED,
                'reviewed_at' => now(),
                'supervisor_notes' => $data['supervisor_notes'] ?? null,
                'project_assignment_id' => $assignment->id,
            ]);

            return $application->refresh()->load([
                'projectTemplate',
                'student',
                'projectAssignment.supervisor',
                'projectAssignment.members.student',
            ]);
        });
    }

    private function findOpenTeamAssignment(
        int $projectTemplateId,
        int $supervisorId
    ): ?ProjectAssignment {
        $openAssignments = ProjectAssignment::query()
            ->where('project_template_id', $projectTemplateId)
            ->where('supervisor_id', $supervisorId)
            ->where('status', ProjectAssignmentStatus::ASSIGNED->value)
            ->lockForUpdate()
            ->get();

        if ($openAssignments->count() > 1) {
            throw new DomainException(
                'يوجد أكثر من فريق مفتوح لهذا القالب. يجب إغلاق أو معالجة التعارض قبل قبول طالب جديد. '.
                '| More than one open team exists for this project template.'
            );
        }

        return $openAssignments->first();
    }

    private function ensureTeamCanAcceptStudent(
        ProjectAssignment $assignment,
        ProjectTemplate $template,
        int $studentId
    ): void {
        /*
         * الفريق يبقى مفتوحاً فقط قبل بدء توزيع المهام.
         */
        if ($assignment->status !== ProjectAssignmentStatus::ASSIGNED) {
            throw new DomainException(
                'لا يمكن إضافة طالب إلى مشروع بدأ أو انتهى. '.
                '| Students cannot be added after the project has started.'
            );
        }

        $hasAssignedTasks = $assignment->assignmentTasks()
            ->whereNotNull('assigned_student_id')
            ->exists();

        if ($hasAssignedTasks) {
            throw new DomainException(
                'لا يمكن إضافة طالب بعد بدء إسناد مهام المشروع. '.
                '| Students cannot be added after project tasks have been assigned.'
            );
        }

        $studentAlreadyExists = $assignment->members()
            ->where('student_id', $studentId)
            ->exists();

        if ($studentAlreadyExists) {
            throw new DomainException(
                'هذا الطالب عضو في فريق المشروع مسبقاً. '.
                '| This student is already a member of the project team.'
            );
        }

        /*
         * null تعني أن القالب القديم لا يضع سقفاً للطلاب.
         */
        if ($template->max_students !== null) {
            $activeMembersCount = $assignment->members()
                ->where('status', self::ACTIVE_MEMBER_STATUS)
                ->count();

            if ($activeMembersCount >= (int) $template->max_students) {
                throw new DomainException(
                    'تم الوصول إلى العدد الأقصى للطلاب في هذا المشروع. '.
                    '| The maximum number of students for this project has been reached.'
                );
            }
        }
    }

    private function ensureApplicationIsPending(
        ProjectTemplateApplication $application
    ): void {
        if ($application->status !== ProjectTemplateApplicationStatus::PENDING) {
            throw new DomainException(
                'لا يمكن اتخاذ قرار على طلب تمت مراجعته مسبقاً. '.
                '| This application has already been reviewed.'
            );
        }
    }
}
