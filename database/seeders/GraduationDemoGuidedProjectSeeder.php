<?php

namespace Database\Seeders;

use App\Domains\Student\Actions\ApplyToProjectTemplateAction;
use App\Domains\Student\Actions\StartAssignmentTaskAction;
use App\Domains\Student\Actions\SubmitAssignmentTaskAction;
use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Domains\Supervisor\Actions\AcceptProjectTemplateApplicationAction;
use App\Domains\Supervisor\Actions\ApproveAssignmentTaskAction;
use App\Domains\Supervisor\Actions\AssignAssignmentTaskToStudentAction;
use App\Domains\Supervisor\Actions\AssignProjectAction;
use App\Domains\Supervisor\Actions\CreateProjectTaskAction;
use App\Domains\Supervisor\Actions\CreateProjectTemplateAction;
use App\Domains\Supervisor\Actions\RecalculateProjectAssignmentProgressAction;
use App\Domains\Supervisor\Actions\StartAssignmentTaskReviewAction;
use App\Domains\Supervisor\Actions\SyncProjectTaskToAssignmentsAction;
use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Models\ProjectAssignment;
use App\Models\ProjectAssignmentTask;
use App\Models\ProjectEvaluation;
use App\Models\ProjectTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GraduationDemoGuidedProjectSeeder extends Seeder
{
    private const STUDENT_EMAIL = 'leleen830@gmail.com';

    private const SUPERVISOR_EMAIL = 'ihsaskhatib35@gmail.com';

    private const TEMPLATE_TITLE = 'Inventory Management REST API';

    private const TASKS = [
        [
            'title' => 'Database Design',
            'description' => 'Design the relational database for products, categories, inventory quantities, and stock movements. Define keys, relationships, constraints, and the SQL schema required by the API.',
            'estimated_hours' => 4,
            'github_branch_or_link' => 'feature/database-schema',
            'order_index' => 1,
        ],
        [
            'title' => 'Authentication & Authorization',
            'description' => 'Implement Flask authentication and protect inventory endpoints so authenticated users can access the operations allowed by their role.',
            'estimated_hours' => 5,
            'github_branch_or_link' => 'feature/auth',
            'order_index' => 2,
        ],
        [
            'title' => 'Inventory CRUD REST API',
            'description' => 'Build the Flask REST API for products, categories, and stock operations with JSON requests, input validation, SQL persistence, and appropriate HTTP status codes.',
            'estimated_hours' => 7,
            'github_branch_or_link' => 'feature/inventory-api',
            'order_index' => 3,
        ],
        [
            'title' => 'Testing & API Documentation',
            'description' => 'Test success and failure cases, verify validation and error handling, and document the API endpoints and expected request/response payloads.',
            'estimated_hours' => 4,
            'github_branch_or_link' => 'feature/testing-docs',
            'order_index' => 4,
        ],
    ];

    public function run(): void
    {
        $this->ensureSafeEnvironment();

        $student = $this->resolveRequiredUser(
            self::STUDENT_EMAIL,
            'student',
            'student'
        );

        $supervisor = $this->resolveRequiredUser(
            self::SUPERVISOR_EMAIL,
            'supervisor',
            'supervisor'
        );

        $this->assertLearningJourneyExists($student);

        $originalUser = Auth::user();

        try {
            $result = DB::transaction(function () use ($student, $supervisor): array {
                $this->removePreviousUnevaluatedDemoProject();

                // 1) Supervisor creates the template and opens its assignment.
                Auth::setUser($supervisor);

                $template = app(CreateProjectTemplateAction::class)->execute([
                    'title' => self::TEMPLATE_TITLE,
                    'description' => 'A guided Backend project that turns the student learning priorities into practical work using Python, Flask, SQL, and Git. The student builds a small inventory-management REST API under supervisor review.',
                    'level' => 'Intermediate',
                    'expected_outcome' => 'A structured Flask REST API with a relational SQL schema, authentication, validated CRUD operations, tests, error handling, and documented endpoints.',
                    'max_students' => 1,
                ]);

                $assignment = app(AssignProjectAction::class)->execute([
                    'project_template_id' => $template->id,
                    'students' => [[
                        'student_id' => $student->id,
                        'role' => 'Backend Developer',
                    ]],
                ]);

                // 2) Create template tasks and use the production sync action.
                foreach (self::TASKS as $taskData) {
                    $projectTask = app(CreateProjectTaskAction::class)
                        ->execute($template, $taskData);

                    app(SyncProjectTaskToAssignmentsAction::class)
                        ->execute($projectTask);
                }

                // 3) Student applies, supervisor accepts into the open team.
                $application = app(ApplyToProjectTemplateAction::class)->execute(
                    projectTemplate: $template,
                    studentUserId: $student->id,
                    data: [
                        'message' => 'أرغب بتنفيذ هذا المشروع لتطبيق ما تعلمته في Flask وSQL وتحسين استخدام Git ضمن مشروع عملي.',
                    ]
                );

                Auth::setUser($supervisor);

                $application = app(AcceptProjectTemplateApplicationAction::class)->execute(
                    application: $application,
                    supervisorId: $supervisor->id,
                    data: [
                        'supervisor_notes' => 'تم قبول الطلب وربط المشروع بخطة تطوير الطالب.',
                    ]
                );

                $assignment = ProjectAssignment::query()
                    ->with([
                        'members',
                        'assignmentTasks' => fn ($query) => $query->orderBy('order_index'),
                    ])
                    ->findOrFail($assignment->id);

                if ($assignment->assignmentTasks->count() !== count(self::TASKS)) {
                    throw new RuntimeException(
                        'Project task synchronization failed. Expected '.count(self::TASKS)
                        .', found '.$assignment->assignmentTasks->count().'.'
                    );
                }

                // 4) Supervisor assigns all four synced tasks to Leleen.
                foreach ($assignment->assignmentTasks as $task) {
                    app(AssignAssignmentTaskToStudentAction::class)->execute(
                        task: $task,
                        studentId: $student->id
                    );
                }

                /*
                 * 5) Drive the real task workflow.
                 *    Tasks 1-3 end DONE.
                 *    Task 4 ends UNDER_REVIEW.
                 *    Assignment therefore remains IN_PROGRESS at 75%.
                 */
                foreach ($assignment->assignmentTasks as $index => $task) {
                    Auth::setUser($student);

                    $task = app(StartAssignmentTaskAction::class)
                        ->execute($task->fresh());

                    $task = app(SubmitAssignmentTaskAction::class)->execute(
                        task: $task,
                        data: [
                            // Avoid inventing an external repository URL.
                            'submission_url' => null,
                            'github_branch_or_link' => self::TASKS[$index]['github_branch_or_link'],
                        ]
                    );

                    Auth::setUser($supervisor);

                    $task = app(StartAssignmentTaskReviewAction::class)
                        ->execute($task);

                    if ($index < 3) {
                        $task = app(ApproveAssignmentTaskAction::class)->execute(
                            task: $task,
                            recalculateProgress: app(
                                RecalculateProjectAssignmentProgressAction::class
                            )
                        );

                        $task->update([
                            'supervisor_feedback' => match ($index) {
                                0 => 'Database schema is normalized and the relationships are clear. Approved.',
                                1 => 'Authentication flow is correctly separated from protected inventory operations. Approved.',
                                2 => 'CRUD endpoints, validation, and SQL persistence satisfy the task scope. Approved.',
                            },
                        ]);
                    }
                }

                $this->applyNarrativeTimestamps(
                    $template,
                    $assignment,
                    $application->id
                );

                return [
                    'template_id' => $template->id,
                    'assignment_id' => $assignment->id,
                    'application_id' => $application->id,
                ];
            });

            $this->verifyFinalState(
                $student,
                $supervisor,
                $result['template_id'],
                $result['assignment_id'],
                $result['application_id']
            );

            $this->printSummary(
                $student,
                $supervisor,
                $result['template_id'],
                $result['assignment_id']
            );
        } finally {
            if ($originalUser !== null) {
                Auth::setUser($originalUser);
            }
        }
    }

    private function ensureSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'GraduationDemoGuidedProjectSeeder is allowed only in local or testing environments.'
            );
        }
    }

    private function resolveRequiredUser(
        string $email,
        string $requiredRole,
        string $label
    ): User {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw new RuntimeException(
                "Graduation demo {$label} was not found: {$email}. "
                .'Seed/create the final demo account before running the guided-project seeder.'
            );
        }

        if (method_exists($user, 'hasRole') && ! $user->hasRole($requiredRole)) {
            throw new RuntimeException(
                "User {$email} exists but does not have the required '{$requiredRole}' role."
            );
        }

        return $user;
    }

    private function assertLearningJourneyExists(User $student): void
    {
        $careerPathId = DB::table('career_paths')
            ->where('Name', 'Backend Developer')
            ->value('CareerPathID');

        if (! $careerPathId) {
            throw new RuntimeException('Backend Developer career path was not found.');
        }

        $assessment = DB::table('assessment_sessions')
            ->where('UserID', $student->id)
            ->where('CareerPathID', $careerPathId)
            ->where('Status', 'completed')
            ->latest('AssessmentSessionID')
            ->first();

        if (! $assessment) {
            throw new RuntimeException(
                'Completed Backend assessment is missing for '.self::STUDENT_EMAIL
                .'. Run the CV/assessment demo seeders first.'
            );
        }

        $hasPreparedLearningPlan = DB::table('a_i_learning_plans')
            ->where('AssessmentSessionID', $assessment->AssessmentSessionID)
            ->where('Status', 'generated')
            ->where('AiModelVersion', 'prepared-demo-v1')
            ->exists();

        if (! $hasPreparedLearningPlan) {
            throw new RuntimeException(
                'Prepared graduation learning plan is missing for the latest completed Backend assessment. '
                .'Run GraduationDemoLearningPlanSeeder first.'
            );
        }
    }

    private function removePreviousUnevaluatedDemoProject(): void
    {
        $templateIds = ProjectTemplate::query()
            ->where('title', self::TEMPLATE_TITLE)
            ->pluck('id');

        if ($templateIds->isEmpty()) {
            return;
        }

        $assignmentIds = ProjectAssignment::query()
            ->whereIn('project_template_id', $templateIds)
            ->pluck('id');

        if (
            $assignmentIds->isNotEmpty()
            && ProjectEvaluation::query()
                ->whereIn('project_assignment_id', $assignmentIds)
                ->exists()
        ) {
            throw new RuntimeException(
                'The previous graduation guided project already has an evaluation. '
                .'Refusing to delete evaluated demo data. Use a fresh demo rebuild instead.'
            );
        }

        // Foreign keys cascade to project tasks, assignments, members, and applications.
        ProjectTemplate::query()->whereIn('id', $templateIds)->delete();
    }

    private function applyNarrativeTimestamps(
        ProjectTemplate $template,
        ProjectAssignment $assignment,
        int $applicationId
    ): void {
        $template->forceFill([
            'created_at' => now()->subDays(14),
            'updated_at' => now()->subDays(14),
        ])->save();

        $assignment->forceFill([
            'assigned_at' => now()->subDays(13),
            'created_at' => now()->subDays(13),
            'updated_at' => now()->subHours(2),
        ])->save();

        DB::table('project_template_applications')
            ->where('id', $applicationId)
            ->update([
                'applied_at' => now()->subDays(13)->subHours(2),
                'reviewed_at' => now()->subDays(13),
                'created_at' => now()->subDays(13)->subHours(2),
                'updated_at' => now()->subDays(13),
            ]);

        $tasks = ProjectAssignmentTask::query()
            ->where('project_assignment_id', $assignment->id)
            ->orderBy('order_index')
            ->get();

        $timeline = [
            [
                'started_at' => now()->subDays(11),
                'submitted_at' => now()->subDays(10),
                'reviewed_at' => now()->subDays(10)->addHours(2),
                'completed_at' => now()->subDays(10)->addHours(3),
            ],
            [
                'started_at' => now()->subDays(9),
                'submitted_at' => now()->subDays(7),
                'reviewed_at' => now()->subDays(7)->addHours(2),
                'completed_at' => now()->subDays(7)->addHours(3),
            ],
            [
                'started_at' => now()->subDays(6),
                'submitted_at' => now()->subDays(3),
                'reviewed_at' => now()->subDays(3)->addHours(2),
                'completed_at' => now()->subDays(3)->addHours(3),
            ],
            [
                'started_at' => now()->subDays(2),
                'submitted_at' => now()->subHours(4),
                'reviewed_at' => now()->subHours(2),
                'completed_at' => null,
            ],
        ];

        foreach ($tasks as $index => $task) {
            $task->forceFill($timeline[$index])->save();
        }
    }

    private function verifyFinalState(
        User $student,
        User $supervisor,
        int $templateId,
        int $assignmentId,
        int $applicationId
    ): void {
        $template = ProjectTemplate::query()
            ->with('tasks')
            ->findOrFail($templateId);

        if (
            $template->title !== self::TEMPLATE_TITLE
            || $template->level !== 'Intermediate'
            || (int) $template->max_students !== 1
            || (int) $template->created_by_id !== (int) $supervisor->id
            || $template->tasks->count() !== count(self::TASKS)
        ) {
            throw new RuntimeException('Guided project template verification failed.');
        }

        $assignment = ProjectAssignment::query()
            ->with([
                'members',
                'assignmentTasks' => fn ($query) => $query->orderBy('order_index'),
            ])
            ->findOrFail($assignmentId);

        if ((int) $assignment->supervisor_id !== (int) $supervisor->id) {
            throw new RuntimeException('Guided project supervisor mismatch.');
        }

        if ($assignment->status !== ProjectAssignmentStatus::IN_PROGRESS) {
            throw new RuntimeException(
                'Expected assignment status in_progress, found '.$assignment->status->value.'.'
            );
        }

        if ((int) $assignment->progress_percentage !== 75) {
            throw new RuntimeException(
                'Expected guided project progress 75%, found '
                .$assignment->progress_percentage.'%.'
            );
        }

        $activeMember = $assignment->members->first(
            fn ($member) => (int) $member->student_id === (int) $student->id
                && $member->status === 'active'
        );

        if (! $activeMember) {
            throw new RuntimeException('Demo student is not an active project member.');
        }

        if ($assignment->assignmentTasks->count() !== count(self::TASKS)) {
            throw new RuntimeException('Assignment task count mismatch.');
        }

        foreach ($assignment->assignmentTasks as $index => $task) {
            if ((int) $task->assigned_student_id !== (int) $student->id) {
                throw new RuntimeException('A guided-project task is assigned to the wrong student.');
            }

            $expected = $index < 3
                ? ProjectAssignmentTaskStatus::DONE
                : ProjectAssignmentTaskStatus::UNDER_REVIEW;

            if ($task->status !== $expected) {
                throw new RuntimeException(
                    'Task #'.($index + 1).' status mismatch. Expected '
                    .$expected->value.', found '.$task->status->value.'.'
                );
            }
        }

        $application = DB::table('project_template_applications')
            ->where('id', $applicationId)
            ->first();

        if (
            ! $application
            || $application->status !== ProjectTemplateApplicationStatus::ACCEPTED->value
            || (int) $application->project_assignment_id !== $assignmentId
        ) {
            throw new RuntimeException('Accepted project application verification failed.');
        }

        if (
            ProjectEvaluation::query()
                ->where('project_assignment_id', $assignmentId)
                ->exists()
        ) {
            throw new RuntimeException('Guided project must be evaluation-free at this stage.');
        }
    }

    private function printSummary(
        User $student,
        User $supervisor,
        int $templateId,
        int $assignmentId
    ): void {
        $template = ProjectTemplate::query()->findOrFail($templateId);

        $assignment = ProjectAssignment::query()
            ->with([
                'assignmentTasks' => fn ($query) => $query->orderBy('order_index'),
            ])
            ->findOrFail($assignmentId);

        $this->command?->newLine();
        $this->command?->info('Graduation guided project seeded successfully.');
        $this->command?->line(
            'Project: '.$template->title
            .' | Template #'.$template->id
            .' | Assignment #'.$assignment->id
        );
        $this->command?->line(
            'Student: '.$student->email.' | Supervisor: '.$supervisor->email
        );
        $this->command?->line(
            'Assignment state: '.$assignment->status->value
            .' | Progress: '.$assignment->progress_percentage.'%'
        );

        $rows = $assignment->assignmentTasks
            ->map(fn (ProjectAssignmentTask $task) => [
                $task->order_index,
                $task->title,
                $task->status->value,
                $task->estimated_hours,
                $task->github_branch_or_link ?? '-',
            ])
            ->all();

        $this->command?->table(
            ['#', 'Task', 'Status', 'Hours', 'Branch / Link'],
            $rows
        );

        $this->command?->info(
            'Live defense checkpoint ready: Task #4 is under_review.'
        );
        $this->command?->line(
            'Approve Task #4 through the real supervisor API/UI. '
            .'RecalculateProjectAssignmentProgressAction should then move '
            .'the assignment from 75% in_progress to 100% under_review.'
        );
        $this->command?->line(
            'At 100% under_review, the final project evaluation endpoint has valid business preconditions.'
        );

        $criteriaCount = DB::table('evaluation_criteria')
            ->where('is_active', true)
            ->count();

        if ($criteriaCount > 0) {
            $this->command?->info(
                "Evaluation readiness: {$criteriaCount} active evaluation criteria are available."
            );
        } else {
            $this->command?->warn(
                'Evaluation readiness warning: no active evaluation criteria were found.'
            );
        }

        $this->command?->warn(
            'No external GitHub submission URL was invented; submissions use branch names only.'
        );
    }
}
