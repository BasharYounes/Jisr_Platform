<?php

namespace Database\Seeders;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Company;
use App\Models\CompanyTask;
use App\Models\CompanyTaskApplication;
use App\Models\CompanyTaskAssignment;
use App\Models\Conversation;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Services\CompanyTasks\CompanyTaskApplicationService;
use App\Services\CompanyTasks\CompanyTaskService;
use App\Services\CompanyTasks\StudentTaskService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class ComplaintCompanyTaskFlowSeeder extends Seeder
{
    private const STUDENT_EMAIL = 'complaint.task.student@jisr.test';

    private const COMPANY_EMAIL = 'complaint.task.company@jisr.test';

    private const PASSWORD = 'password';

    private const COMPANY_WEBSITE = 'https://complaint-task-demo.jisr.test';

    private const TASK_TITLE = 'Complaint Flow - Company Task Assignment';

    public function run(): void
    {
        Queue::fake([
            SendFirebaseNotificationJob::class,
        ]);

        $result = DB::transaction(function (): array {
            // STEP 1: deterministic demo users.
            $student = $this->upsertUser(
                email: self::STUDENT_EMAIL,
                name: 'Complaint Flow Demo Student',
                role: 'student',
                isCompany: false,
            );

            $companyUser = $this->upsertUser(
                email: self::COMPANY_EMAIL,
                name: 'Complaint Flow Demo Company',
                role: 'company',
                isCompany: true,
            );

            // STEP 2: company + its actual representative.
            $company = Company::query()->updateOrCreate(
                [
                    'website' => self::COMPANY_WEBSITE,
                ],
                [
                    'industry' => 'Software Development',
                    'location' => 'Damascus, Syria',
                    'documentation_file' => null,
                ]
            );

            DB::table('company_users')
                ->where('user_id', $companyUser->id)
                ->where('company_id', '!=', $company->id)
                ->delete();

            DB::table('company_users')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'user_id' => $companyUser->id,
                ],
                [
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // STEP 3: student profile.
            DB::table('student_profiles')->updateOrInsert(
                [
                    'user_id' => $student->id,
                ],
                [
                    'university' => 'Jisr University',
                    'major' => 'Software Engineering',
                    'graduation_year' => now()->addYear()->year,
                    'phone' => '+963900000111',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // STEP 4: real skill data used by the matching snapshot.
            $skill = Skill::query()
                ->where('name', 'Laravel')
                ->first();

            if (! $skill) {
                $skill = Skill::query()->create([
                    'name' => 'Laravel',
                    'category' => 'Backend Development',
                    'normalized_name' => 'laravel',
                ]);
            }

            UserSkill::query()->updateOrCreate(
                [
                    'UserId' => $student->id,
                    'SkillId' => $skill->id,
                ],
                [
                    'ProficiencyLevel' => 4,
                    'ConfidenceScore' => 0.95,
                    'Source' => 'manual',
                    'Verified' => true,
                ]
            );

            // Make the dedicated demo workflow rerunnable.
            $this->removePreviousDemoWorkflow($company);

            // STEP 5: COMPANY CREATES TASK via the real service.
            /** @var CompanyTaskService $companyTaskService */
            $companyTaskService = app(CompanyTaskService::class);

            $task = $companyTaskService->createTask(
                companyId: (int) $company->id,
                data: [
                    'title' => self::TASK_TITLE,
                    'description' => 'A deterministic company task used to test the real accepted-task and contextual complaint flow.',
                    'difficulty_level' => 'intermediate',
                    'duration_days' => 7,
                    'deadline' => now()->addDays(14),
                    'max_applicants' => 10,
                    'max_accepted_students' => 1,
                    'deliverables' => [
                        'GitHub repository',
                        'README',
                        'Postman collection',
                    ],
                    'acceptance_criteria' => [
                        'Use Laravel service/repository architecture.',
                        'Return consistent JSON responses.',
                        'Include API tests.',
                    ],
                    'submission_type' => 'github_link',
                    'skills' => [
                        [
                            'skill_id' => (int) $skill->id,
                            'required_level' => 3,
                            'weight' => 1.00,
                            'mandatory' => true,
                        ],
                    ],
                ]
            );

            $this->assertStatus(
                actual: (string) $task->status,
                expected: 'draft',
                label: 'Task after creation',
            );

            // STEP 6: COMPANY PUBLISHES TASK via the real service.
            $task = $companyTaskService->publishTask(
                companyId: (int) $company->id,
                taskId: (int) $task->id,
            );

            $this->assertStatus(
                actual: (string) $task->status,
                expected: 'published',
                label: 'Task after publishing',
            );

            // STEP 7: STUDENT APPLIES via the real service.
            /** @var StudentTaskService $studentTaskService */
            $studentTaskService = app(StudentTaskService::class);

            $application = $studentTaskService->applyToTask(
                studentUserId: (int) $student->id,
                taskId: (int) $task->id,
                data: [
                    'message' => 'I am applying through the deterministic complaint-flow demo.',
                    'github_url' => 'https://github.com/example/complaint-flow-demo',
                ]
            );

            $this->assertStatus(
                actual: (string) $application->status,
                expected: 'pending',
                label: 'Application after student apply',
            );

            // STEP 8: COMPANY ACCEPTS via the real business service.
            /** @var CompanyTaskApplicationService $applicationService */
            $applicationService = app(CompanyTaskApplicationService::class);

            $applicationService->acceptApplication(
                companyId: (int) $company->id,
                applicationId: (int) $application->id,
                data: [
                    'company_notes' => 'Accepted for contextual complaint API testing.',
                ]
            );

            // acceptApplication currently does the transition but returns no assignment.
            $application->refresh();
            $task->refresh();

            $assignment = CompanyTaskAssignment::query()
                ->where('company_task_application_id', $application->id)
                ->where('student_user_id', $student->id)
                ->firstOrFail();

            $this->assertStatus(
                actual: (string) $application->status,
                expected: 'accepted',
                label: 'Application after company acceptance',
            );

            $this->assertStatus(
                actual: (string) $assignment->status,
                expected: 'working',
                label: 'Assignment after company acceptance',
            );

            $this->assertStatus(
                actual: (string) $task->status,
                expected: 'in_progress',
                label: 'Task after company acceptance',
            );

            // STEP 9: verify the real conversation context used by complaints.
            $conversation = Conversation::query()
                ->where(
                    'conversationable_type',
                    $assignment->getMorphClass()
                )
                ->where(
                    'conversationable_id',
                    $assignment->id
                )
                ->firstOrFail();

            $participants = DB::table('conversation_participants')
                ->where('conversation_id', $conversation->id)
                ->get(['user_id', 'role']);

            $hasCompanyParticipant = $participants->contains(
                fn ($participant): bool => (int) $participant->user_id === (int) $companyUser->id
                    && $participant->role === 'company'
            );

            $hasStudentParticipant = $participants->contains(
                fn ($participant): bool => (int) $participant->user_id === (int) $student->id
                    && $participant->role === 'student'
            );

            if (! $hasCompanyParticipant || ! $hasStudentParticipant) {
                throw new \RuntimeException(
                    'The task assignment conversation was created without the expected company/student participants.'
                );
            }

            return [
                'student' => $student,
                'company_user' => $companyUser,
                'company' => $company,
                'task' => $task,
                'application' => $application,
                'assignment' => $assignment,
                'conversation' => $conversation,
            ];
        });

        $this->printSummary($result);
    }

    private function upsertUser(
        string $email,
        string $name,
        string $role,
        bool $isCompany
    ): User {
        /*
         * User::$fillable intentionally excludes email_verified and
         * is_verified_by_admin, so forceFill is required for deterministic
         * test accounts.
         */
        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user) {
            $user = new User;
        }

        $attributes = [
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'is_active' => true,
            'email_verified' => true,
        ];

        if ($isCompany) {
            $attributes['is_verified_by_admin'] = 'accepted';
        }

        $user->forceFill($attributes);
        $user->save();

        $roleModel = Role::findOrCreate($role, 'web');

        if (! $user->hasRole($role)) {
            $user->assignRole($roleModel);
        }

        return $user;
    }

    private function removePreviousDemoWorkflow(Company $company): void
    {
        $tasks = CompanyTask::withTrashed()
            ->where('company_id', $company->id)
            ->where('title', self::TASK_TITLE)
            ->get();

        if ($tasks->isEmpty()) {
            return;
        }

        $assignmentMorphType = (new CompanyTaskAssignment)->getMorphClass();

        foreach ($tasks as $task) {
            $assignmentIds = CompanyTaskAssignment::withTrashed()
                ->where('company_task_id', $task->id)
                ->pluck('id');

            if ($assignmentIds->isNotEmpty()) {
                $conversationIds = DB::table('conversations')
                    ->where('conversationable_type', $assignmentMorphType)
                    ->whereIn('conversationable_id', $assignmentIds)
                    ->pluck('id');

                if ($conversationIds->isNotEmpty()) {
                    if (Schema::hasTable('messages')) {
                        DB::table('messages')
                            ->whereIn('conversation_id', $conversationIds)
                            ->delete();
                    }

                    if (Schema::hasTable('conversation_participants')) {
                        DB::table('conversation_participants')
                            ->whereIn('conversation_id', $conversationIds)
                            ->delete();
                    }

                    DB::table('conversations')
                        ->whereIn('id', $conversationIds)
                        ->delete();
                }

                if (
                    Schema::hasTable('complaints')
                    && Schema::hasColumn('complaints', 'context_type')
                    && Schema::hasColumn('complaints', 'context_id')
                ) {
                    DB::table('complaints')
                        ->where(
                            'context_type',
                            'company_task_assignment'
                        )
                        ->whereIn('context_id', $assignmentIds)
                        ->delete();
                }
            }

            $task->forceDelete();
        }
    }

    private function assertStatus(
        string $actual,
        string $expected,
        string $label
    ): void {
        if ($actual !== $expected) {
            throw new \RuntimeException(
                "{$label}: expected [{$expected}], got [{$actual}]."
            );
        }
    }

    private function printSummary(array $result): void
    {
        /** @var User $student */
        $student = $result['student'];

        /** @var User $companyUser */
        $companyUser = $result['company_user'];

        /** @var Company $company */
        $company = $result['company'];

        /** @var CompanyTask $task */
        $task = $result['task'];

        /** @var CompanyTaskApplication $application */
        $application = $result['application'];

        /** @var CompanyTaskAssignment $assignment */
        $assignment = $result['assignment'];

        /** @var Conversation $conversation */
        $conversation = $result['conversation'];

        $this->command?->newLine();
        $this->command?->info(
            'Complaint Company Task real flow seeded successfully.'
        );

        $this->command?->line('');
        $this->command?->line('STUDENT LOGIN');
        $this->command?->line('  Email: '.self::STUDENT_EMAIL);
        $this->command?->line('  Password: '.self::PASSWORD);
        $this->command?->line('  User ID: '.$student->id);

        $this->command?->line('');
        $this->command?->line('COMPANY LOGIN');
        $this->command?->line('  Email: '.self::COMPANY_EMAIL);
        $this->command?->line('  Password: '.self::PASSWORD);
        $this->command?->line('  User ID: '.$companyUser->id);
        $this->command?->line('  Company ID: '.$company->id);

        $this->command?->line('');
        $this->command?->line('REAL FLOW RESULT');
        $this->command?->line(
            '  Task ID: '.$task->id.' | status='.$task->status
        );
        $this->command?->line(
            '  Application ID: '.$application->id
            .' | status='.$application->status
        );
        $this->command?->line(
            '  Assignment ID: '.$assignment->id
            .' | status='.$assignment->status
        );
        $this->command?->line(
            '  Conversation ID: '.$conversation->id
        );

        $this->command?->line('');
        $this->command?->info(
            'After logging in as the student, call:'
        );
        $this->command?->line(
            '  GET /api/student/tasks/accepted'
        );

        $this->command?->info(
            'Then create the student -> company complaint with:'
        );
        $this->command?->line(
            '  POST /api/complaints'
        );
        $this->command?->line(
            '  context_type = company_task_assignment'
        );
        $this->command?->line(
            '  context_id = '.$assignment->id
        );
        $this->command?->newLine();
    }
}
