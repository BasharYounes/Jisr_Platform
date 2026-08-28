<?php

namespace Tests\Feature\Supervisor;

use App\Domains\Supervisor\Actions\ApproveProjectEvaluationAction;
use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\PortfolioProject;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use App\Models\ProjectTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Support\NotificationTypes;

class ProjectEvaluationPortfolioCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_portfolio_is_created_only_after_all_active_student_evaluations_are_approved(): void
    {
        Queue::fake();

        $lead = $this->makeUser(
            'portfolio.lead@test.com'
        );

        $supervisor = $this->makeUser(
            'portfolio.supervisor@test.com'
        );

        $firstStudent = $this->makeUser(
            'portfolio.student.one@test.com'
        );

        $secondStudent = $this->makeUser(
            'portfolio.student.two@test.com'
        );

        Auth::login($lead);

        $template = ProjectTemplate::query()->create([
            'title' => '[TEST] Team Portfolio Project',
            'description' => 'Project used to verify automatic portfolio creation.',
            'level' => 'Intermediate',
            'expected_outcome' => 'Verified portfolio automation.',
            'max_students' => 2,
            'created_by_type' => 'supervisor',
            'created_by_id' => $supervisor->id,
        ]);

        $assignment = ProjectAssignment::query()->create([
            'project_template_id' => $template->id,
            'supervisor_id' => $supervisor->id,
            'status' => ProjectAssignmentStatus::UNDER_REVIEW->value,
            'progress_percentage' => 100,
            'assigned_at' => now()->subDays(3),
            'submitted_at' => now()->subDays(2),
        ]);

        $assignment->members()->createMany([
            [
                'student_id' => $firstStudent->id,
                'role' => 'Backend Developer',
                'status' => 'active',
            ],
            [
                'student_id' => $secondStudent->id,
                'role' => 'Backend Developer',
                'status' => 'active',
            ],
        ]);

        $assignment->assignmentTasks()->create([
            'title' => 'Completed project task',
            'description' => 'Task required for final project approval.',
            'status' => ProjectAssignmentTaskStatus::DONE->value,
            'completed_at' => now()->subDays(2),
            'order_index' => 1,
        ]);

        $firstEvaluation = $this->makeEvaluation(
            assignment: $assignment,
            student: $firstStudent,
            supervisor: $supervisor,
            grade: 81.00
        );

        $secondEvaluation = $this->makeEvaluation(
            assignment: $assignment,
            student: $secondStudent,
            supervisor: $supervisor,
            grade: 92.50
        );

        $action = app(
            ApproveProjectEvaluationAction::class
        );

        /*
         * اعتماد الطالب الأول فقط:
         * تقييمه يصبح approved،
         * لكن المشروع لم يكتمل بعد،
         * لذلك لا يجب إنشاء أي PortfolioProject.
         */
        $action->execute($firstEvaluation);

        $this->assertSame(
            ProjectEvaluationStatus::APPROVED->value,
            $firstEvaluation->fresh()->status
        );

        $this->assertSame(
            ProjectAssignmentStatus::UNDER_REVIEW->value,
            $assignment->fresh()->status->value
        );

        $this->assertDatabaseCount(
            'portfolio_projects',
            0
        );

        /*
         * عند اعتماد آخر طالب نشط:
         * جميع التقييمات أصبحت approved،
         * المشروع يصبح completed،
         * الـEvent ينطلق،
         * والـListener ينشئ Portfolio لكل طالب.
         */
        $action->execute($secondEvaluation);

        $this->assertSame(
            ProjectEvaluationStatus::APPROVED->value,
            $secondEvaluation->fresh()->status
        );

        $this->assertSame(
            ProjectAssignmentStatus::COMPLETED->value,
            $assignment->fresh()->status->value
        );

        $this->assertDatabaseCount(
            'portfolio_projects',
            2
        );

        $this->assertDatabaseHas(
            'portfolio_projects',
            [
                'user_id' => $firstStudent->id,
                'source' => 'project_assignment',
                'portfolioable_type' => $assignment->getMorphClass(),
                'portfolioable_id' => $assignment->id,
                'title' => '[TEST] Team Portfolio Project',
                'grade' => 81.00,
            ]
        );

        $this->assertDatabaseHas(
            'portfolio_projects',
            [
                'user_id' => $secondStudent->id,
                'source' => 'project_assignment',
                'portfolioable_type' => $assignment->getMorphClass(),
                'portfolioable_id' => $assignment->id,
                'title' => '[TEST] Team Portfolio Project',
                'grade' => 92.50,
            ]
        );

        $this->assertDatabaseHas(
            'notifications',
            [
                'user_id' => $firstStudent->id,
                'actor_id' => $lead->id,
                'type' => NotificationTypes::PROJECT_STATUS_CHANGED,
                'notifiable_type' => $assignment->getMorphClass(),
                'notifiable_id' => $assignment->id,
            ]
        );

        $this->assertDatabaseHas(
            'notifications',
            [
                'user_id' => $secondStudent->id,
                'actor_id' => $lead->id,
                'type' => NotificationTypes::PROJECT_STATUS_CHANGED,
                'notifiable_type' => $assignment->getMorphClass(),
                'notifiable_id' => $assignment->id,
            ]
        );

        $this->assertSame(
            2,
            \App\Models\Notification::query()
                ->where(
                    'type',
                    NotificationTypes::PROJECT_STATUS_CHANGED
                )
                ->where(
                    'notifiable_type',
                    $assignment->getMorphClass()
                )
                ->where(
                    'notifiable_id',
                    $assignment->id
                )
                ->count()
        );

        /*
         * تأكيد إضافي أن كل طالب حصل على سجل مستقل
         * لنفس ProjectAssignment.
         */
        $portfolioProjects = PortfolioProject::query()
            ->where(
                'portfolioable_type',
                $assignment->getMorphClass()
            )
            ->where(
                'portfolioable_id',
                $assignment->id
            )
            ->get();

        $this->assertCount(
            2,
            $portfolioProjects
        );

        $this->assertEqualsCanonicalizing(
            [
                $firstStudent->id,
                $secondStudent->id,
            ],
            $portfolioProjects
                ->pluck('user_id')
                ->all()
        );
    }

    private function makeEvaluation(
        ProjectAssignment $assignment,
        User $student,
        User $supervisor,
        float $grade
    ): ProjectEvaluation {
        return ProjectEvaluation::query()->create([
            'project_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'total_score' => $grade,
            'final_grade' => $grade,
            'status' => ProjectEvaluationStatus::SUBMITTED->value,
            'general_comment' => 'Final test evaluation.',
            'summary_metrics' => [],
            'evaluated_at' => now()->subDays(3),
            'appeal_started_at' => now()->subDays(3),
            'appeal_deadline_at' => now()->subDay(),
        ]);
    }

    private function makeUser(
        string $email
    ): User {
        return User::query()->create([
            'name' => ucfirst(
                str_replace(
                    ['.', '@test.com'],
                    [' ', ''],
                    $email
                )
            ),
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
