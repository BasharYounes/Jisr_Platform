<?php

namespace Database\Seeders;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Enums\SupervisorSpecialization;
use App\Models\EvaluationCriteria;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use App\Models\ProjectTemplate;
use App\Models\StudentProfile;
use App\Models\SupervisorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class FrontendEvaluationAppealDemoSeeder extends Seeder
{
    private const SUPERVISOR_EMAIL = 'frontend.evaluation.supervisor@test.com';

    private const STUDENT_EMAIL = 'leleen830@gmail.com';

    private const PASSWORD = '12345678';

    private const PROJECT_TITLE = '[FRONTEND DEMO] Evaluation & Appeal Project';

    private const SUPERVISOR_TOKEN_NAME = 'frontend-evaluation-demo-supervisor';

    private const STUDENT_TOKEN_NAME = 'frontend-evaluation-demo-student';

    public function run(): void
    {
        Role::firstOrCreate([
            'name' => 'supervisor',
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'student',
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $result = DB::transaction(function (): array {
            $supervisor = $this->upsertDemoUser(
                email: self::SUPERVISOR_EMAIL,
                name: 'Frontend Evaluation Demo Supervisor'
            );

            $student = $this->upsertDemoUser(
                email: self::STUDENT_EMAIL,
                name: 'Frontend Evaluation Demo Student'
            );

            // Dedicated demo accounts: keep their roles deterministic on every run.
            $supervisor->syncRoles(['supervisor']);
            $student->syncRoles(['student']);

            SupervisorProfile::updateOrCreate(
                ['user_id' => $supervisor->id],
                [
                    'specialization' => SupervisorSpecialization::Backend->value,
                    'is_volunteer' => false,
                ]
            );

            StudentProfile::updateOrCreate(
                ['user_id' => $student->id],
                [
                    'university' => 'Jisr Demo University',
                    'major' => 'Software Engineering',
                    'graduation_year' => now()->year + 1,
                    'phone' => '+963900000000',
                ]
            );

            /*
             * Idempotency without damaging unrelated development data:
             * remove only this seeder's previous project tree.
             * Assignment/evaluation/task/member/appeal children are removed by FKs.
             */
            ProjectTemplate::query()
                ->where('title', self::PROJECT_TITLE)
                ->where('created_by_type', 'supervisor')
                ->where('created_by_id', $supervisor->id)
                ->get()
                ->each(fn (ProjectTemplate $template) => $template->delete());

            $template = ProjectTemplate::create([
                'title' => self::PROJECT_TITLE,
                'description' => 'Stable demo project used by the frontend team to test evaluation details, project evaluation lists, summary screens, and student appeals.',
                'level' => 'Intermediate',
                'expected_outcome' => 'A completed two-task backend project with a submitted final evaluation and an open 48-hour appeal window.',
                'max_students' => 1,
                'created_by_type' => 'supervisor',
                'created_by_id' => $supervisor->id,
            ]);

            $taskOne = $template->tasks()->create([
                'title' => 'Build Authentication Endpoints',
                'description' => 'Implement register, login, logout, validation, and authenticated profile endpoints.',
                'status' => 'todo',
                'estimated_hours' => 5,
                'github_branch_or_link' => 'feature/demo-auth',
                'order_index' => 1,
            ]);

            $taskTwo = $template->tasks()->create([
                'title' => 'Build Products CRUD API',
                'description' => 'Implement RESTful product CRUD endpoints using Form Requests, Resources, authorization, and tests.',
                'status' => 'todo',
                'estimated_hours' => 7,
                'github_branch_or_link' => 'feature/demo-products-crud',
                'order_index' => 2,
            ]);

            $assignment = ProjectAssignment::create([
                'project_template_id' => $template->id,
                'supervisor_id' => $supervisor->id,
                // Final evaluation is valid only while the project is under review.
                'status' => ProjectAssignmentStatus::UNDER_REVIEW->value,
                'progress_percentage' => 100,
                'submission_url' => 'https://example.test/jisr-demo/submission',
                'github_link' => 'https://github.com/example/jisr-frontend-evaluation-demo',
                'assigned_at' => now()->subDays(7),
                'submitted_at' => now()->subHours(3),
            ]);

            $assignment->members()->create([
                'student_id' => $student->id,
                'role' => 'Backend Developer',
                // Evaluation requires the student to be an active project member.
                'status' => 'active',
            ]);

            $assignmentTaskOne = $assignment->assignmentTasks()->create([
                'project_task_id' => $taskOne->id,
                'assigned_student_id' => $student->id,
                'title' => $taskOne->title,
                'description' => $taskOne->description,
                // All assigned student tasks must be DONE before final evaluation.
                'status' => ProjectAssignmentTaskStatus::DONE->value,
                'estimated_hours' => $taskOne->estimated_hours,
                'submission_url' => 'https://example.test/jisr-demo/tasks/auth',
                'github_branch_or_link' => 'feature/demo-auth',
                'supervisor_feedback' => 'Authentication flow is complete and correctly validated.',
                'started_at' => now()->subDays(6),
                'submitted_at' => now()->subDays(4),
                'reviewed_at' => now()->subDays(4)->addHours(2),
                'completed_at' => now()->subDays(4)->addHours(2),
                'order_index' => 1,
            ]);

            $assignmentTaskTwo = $assignment->assignmentTasks()->create([
                'project_task_id' => $taskTwo->id,
                'assigned_student_id' => $student->id,
                'title' => $taskTwo->title,
                'description' => $taskTwo->description,
                'status' => ProjectAssignmentTaskStatus::DONE->value,
                'estimated_hours' => $taskTwo->estimated_hours,
                'submission_url' => 'https://example.test/jisr-demo/tasks/products-crud',
                'github_branch_or_link' => 'feature/demo-products-crud',
                'supervisor_feedback' => 'CRUD implementation is complete with clean resource responses and tests.',
                'started_at' => now()->subDays(4),
                'submitted_at' => now()->subDays(2),
                'reviewed_at' => now()->subDays(2)->addHours(3),
                'completed_at' => now()->subDays(2)->addHours(3),
                'order_index' => 2,
            ]);

            $criteria = collect([
                [
                    'name' => '[FRONTEND DEMO] Code Quality',
                    'description' => 'Code readability, architecture, naming, maintainability, and Laravel conventions.',
                    'category' => 'technical',
                    'max_score' => 10,
                    'weight' => 40,
                    'scoring_anchors' => [
                        '0' => 'No usable implementation.',
                        '5' => 'Working implementation with important quality issues.',
                        '10' => 'Clean, maintainable, production-oriented implementation.',
                    ],
                ],
                [
                    'name' => '[FRONTEND DEMO] Task Completion',
                    'description' => 'Completeness and correctness of the two assigned project tasks.',
                    'category' => 'delivery',
                    'max_score' => 10,
                    'weight' => 35,
                    'scoring_anchors' => [
                        '0' => 'Tasks are not delivered.',
                        '5' => 'Core requirements are partially delivered.',
                        '10' => 'All assigned requirements are completed correctly.',
                    ],
                ],
                [
                    'name' => '[FRONTEND DEMO] Testing & Reliability',
                    'description' => 'Validation, automated testing, edge-case handling, and overall reliability.',
                    'category' => 'quality',
                    'max_score' => 10,
                    'weight' => 25,
                    'scoring_anchors' => [
                        '0' => 'No testing or validation evidence.',
                        '5' => 'Basic validation and partial testing.',
                        '10' => 'Strong automated tests and robust edge-case handling.',
                    ],
                ],
            ])->map(function (array $data): EvaluationCriteria {
                return EvaluationCriteria::updateOrCreate(
                    ['name' => $data['name']],
                    $data + [
                        'skill_impacts' => null,
                        'version' => 1,
                        'is_active' => true,
                        'is_required' => true,
                    ]
                );
            });

            /*
             * Weighted score:
             * 8/10 * 40 + 9/10 * 35 + 9/10 * 25 = 86.00
             */
            $appealStartedAt = now()->subMinutes(5);
            $appealDeadlineAt = $appealStartedAt
                ->copy()
                ->addHours((int) config('evaluations.appeal_window_hours', 48));

            $evaluation = ProjectEvaluation::create([
                'project_assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'supervisor_id' => $supervisor->id,
                'total_score' => 86.00,
                'final_grade' => 86.00,
                // Keep it submitted so the student can submit an appeal.
                'status' => ProjectEvaluationStatus::SUBMITTED->value,
                'general_comment' => 'The student completed both assigned tasks successfully. The implementation is strong overall, with minor room for improvement in code organization and automated test coverage.',
                'summary_metrics' => [
                    'criteria_count' => 3,
                    'total_weight' => 100,
                    'calculated_at' => $appealStartedAt->toISOString(),
                    'demo_dataset' => 'frontend_evaluation_appeal',
                ],
                'evaluated_at' => $appealStartedAt,
                // Appeal window is intentionally open for frontend testing.
                'appeal_started_at' => $appealStartedAt,
                'appeal_deadline_at' => $appealDeadlineAt,
            ]);

            $evaluation->items()->createMany([
                [
                    'evaluation_criteria_id' => $criteria[0]->id,
                    'score' => 8,
                    'comment' => 'Good Laravel structure and naming. A few service responsibilities can be separated further.',
                    'evidence' => 'Reviewed authentication and CRUD implementation structure, request validation, and API resources.',
                    'evidence_urls' => [],
                ],
                [
                    'evaluation_criteria_id' => $criteria[1]->id,
                    'score' => 9,
                    'comment' => 'Both assigned tasks were completed and accepted.',
                    'evidence' => 'Authentication endpoints and products CRUD were both submitted and marked done.',
                    'evidence_urls' => [],
                ],
                [
                    'evaluation_criteria_id' => $criteria[2]->id,
                    'score' => 9,
                    'comment' => 'Validation and test coverage are strong, with only minor missing edge cases.',
                    'evidence' => 'Feature tests cover authentication failures, CRUD success cases, and common validation errors.',
                    'evidence_urls' => [],
                ],
            ]);

            return compact(
                'supervisor',
                'student',
                'template',
                'assignment',
                'assignmentTaskOne',
                'assignmentTaskTwo',
                'evaluation'
            );
        });

        /** @var User $supervisor */
        $supervisor = $result['supervisor'];
        /** @var User $student */
        $student = $result['student'];

        // Keep reruns clean: revoke only this demo seeder's own test tokens.
        $supervisor->tokens()->where('name', self::SUPERVISOR_TOKEN_NAME)->delete();
        $student->tokens()->where('name', self::STUDENT_TOKEN_NAME)->delete();

        $supervisorToken = $supervisor
            ->createToken(self::SUPERVISOR_TOKEN_NAME)
            ->plainTextToken;

        $studentToken = $student
            ->createToken(self::STUDENT_TOKEN_NAME)
            ->plainTextToken;

        $this->printSummary(
            result: $result,
            supervisorToken: $supervisorToken,
            studentToken: $studentToken
        );
    }

    private function upsertDemoUser(string $email, string $name): User
    {
        /*
         * The users table has a deleted_at column, but the current User model
         * does not use Laravel's SoftDeletes trait. Therefore withTrashed(),
         * trashed(), and restore() are not available on this model.
         *
         * Because there is no SoftDeletes global scope on User, a normal query
         * can still find a row whose deleted_at is not null. We explicitly clear
         * deleted_at so rerunning this demo seeder also revives its own demo user
         * without changing the application's User model behavior.
         */
        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user === null) {
            $user = new User;
        }

        /*
         * forceFill is intentional here. The current User::$fillable contains
         * only name, email, password, and is_active, while this demo also needs
         * email_verified, is_verified_by_admin, bio, and deleted_at.
         */
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'is_active' => true,
            'email_verified' => true,
            'is_verified_by_admin' => 'accepted',
            'bio' => 'Dedicated account created by FrontendEvaluationAppealDemoSeeder.',
            'deleted_at' => null,
        ]);

        $user->save();

        return $user;
    }

    private function printSummary(
        array $result,
        string $supervisorToken,
        string $studentToken
    ): void {
        if ($this->command === null) {
            return;
        }

        /** @var ProjectAssignment $assignment */
        $assignment = $result['assignment'];
        /** @var ProjectEvaluation $evaluation */
        $evaluation = $result['evaluation'];

        $this->command->newLine();
        $this->command->info('Frontend evaluation + appeal demo data is ready.');
        $this->command->newLine();

        $this->command->table(
            ['Item', 'Value'],
            [
                ['Supervisor ID', $result['supervisor']->id],
                ['Supervisor email', self::SUPERVISOR_EMAIL],
                ['Student ID', $result['student']->id],
                ['Student email', self::STUDENT_EMAIL],
                ['Password (both accounts)', self::PASSWORD],
                ['Project template ID', $result['template']->id],
                ['Project assignment ID', $assignment->id],
                ['Assignment status', ProjectAssignmentStatus::UNDER_REVIEW->value],
                ['Task #1 assignment ID', $result['assignmentTaskOne']->id],
                ['Task #2 assignment ID', $result['assignmentTaskTwo']->id],
                ['Evaluation ID', $evaluation->id],
                ['Evaluation status', ProjectEvaluationStatus::SUBMITTED->value],
                ['Final grade', '86.00'],
                ['Appeal deadline', $evaluation->appeal_deadline_at?->toDateTimeString()],
            ]
        );

        $this->command->newLine();
        $this->command->warn('SUPERVISOR BEARER TOKEN:');
        $this->command->line($supervisorToken);
        $this->command->newLine();
        $this->command->warn('STUDENT BEARER TOKEN:');
        $this->command->line($studentToken);
        $this->command->newLine();

        $this->command->info('Useful API checks:');
        $this->command->line("GET  /api/supervisor/project-assignments/{$assignment->id}/active-students");
        $this->command->line("GET  /api/supervisor/project-assignments/{$assignment->id}/evaluations");
        $this->command->line("GET  /api/supervisor/project-assignments/{$assignment->id}/evaluations/summary");
        $this->command->line("GET  /api/supervisor/project-evaluations/{$evaluation->id}");
        $this->command->line("GET  /api/student/project-evaluations/{$evaluation->id}");
        $this->command->line("POST /api/student/project-evaluations/{$evaluation->id}/appeals");
        $this->command->newLine();
    }
}
