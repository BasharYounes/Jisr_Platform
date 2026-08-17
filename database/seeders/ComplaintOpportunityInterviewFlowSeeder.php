<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Company;
use App\Models\CV;
use App\Models\Opportunity;
use App\Models\OpportunityInterview;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Services\Opportunities\CompanyOpportunityService;
use App\Services\Opportunities\OpportunityInterviewService;
use App\Services\Opportunities\StudentOpportunityApplicationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class ComplaintOpportunityInterviewFlowSeeder extends Seeder
{
    private const STUDENT_EMAIL = 'complaint.task.student@jisr.test';

    private const COMPANY_EMAIL = 'complaint.task.company@jisr.test';

    private const PASSWORD = 'password';

    private const COMPANY_WEBSITE = 'https://complaint-task-demo.jisr.test';

    private const OPPORTUNITY_TITLE = 'Complaint Flow - Opportunity Interview';

    public function run(): void
    {
        $result = DB::transaction(function (): array {
            /*
             * STEP 1: Resolve/create deterministic Student + Company users.
             */
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

            /*
             * STEP 2: Resolve/create the same dedicated demo company used
             * by the Company Task complaint flow.
             */
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

            /*
             * STEP 3: Ensure the Student has the profile/skill data used
             * by the real opportunity matching flow.
             */
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

            /*
             * STEP 4: StudentOpportunityApplicationService requires a CV.
             * We create a deterministic local demo CV record.
             */
            CV::query()->updateOrCreate(
                [
                    'UserId' => $student->id,
                    'FileUrl' => 'demo/complaint-opportunity-interview-cv.pdf',
                ],
                [
                    'IsPrimary' => true,
                    'UploadedAt' => now(),
                ]
            );

            /*
             * STEP 5: Make the seeder safely repeatable.
             * Only this dedicated opportunity flow is removed.
             */
            $this->removePreviousDemoWorkflow(
                company: $company,
                student: $student,
            );

            /*
             * STEP 6: COMPANY CREATES OPPORTUNITY.
             *
             * This uses the same service as POST /api/company/opportunities.
             * The service creates it as "draft" and syncs the required skill.
             */
            /** @var CompanyOpportunityService $companyOpportunityService */
            $companyOpportunityService = app(CompanyOpportunityService::class);

            $opportunity = $companyOpportunityService->createOpportunity(
                companyId: (int) $company->id,
                data: [
                    'title' => self::OPPORTUNITY_TITLE,
                    'description' => 'A deterministic published internship used to test the real Opportunity Interview complaint flow.',
                    'type' => 'internship',
                    'location' => 'Remote',
                    'salary_min' => 200,
                    'salary_max' => 400,
                    'deadline' => now()->addDays(14),
                    'skills' => [
                        [
                            'skill_id' => (int) $skill->id,
                            'required_level' => 3,
                            'mandatory' => true,
                            'weight' => 1.00,
                        ],
                    ],
                ]
            );

            $this->assertStatus(
                actual: (string) $opportunity->status,
                expected: 'draft',
                label: 'Opportunity after creation',
            );

            /*
             * STEP 7: COMPANY PUBLISHES OPPORTUNITY.
             */
            $opportunity = $companyOpportunityService->publishOpportunity(
                companyId: (int) $company->id,
                opportunityId: (int) $opportunity->id,
            );

            $this->assertStatus(
                actual: (string) $opportunity->status,
                expected: 'published',
                label: 'Opportunity after publishing',
            );

            /*
             * STEP 8: STUDENT APPLIES.
             *
             * This uses the real application service:
             * - verifies the opportunity is published/active
             * - requires the student's CV
             * - calculates the real matching snapshot
             * - creates the application as pending
             */
            /** @var StudentOpportunityApplicationService $applicationService */
            $applicationService = app(
                StudentOpportunityApplicationService::class
            );

            $application = $applicationService->apply(
                studentId: (int) $student->id,
                opportunityId: (int) $opportunity->id,
                data: [
                    'cover_letter' => 'I am applying through the deterministic Opportunity Interview complaint-flow demo.',
                ]
            );

            $this->assertStatus(
                actual: (string) $application->status,
                expected: 'pending',
                label: 'Application after student apply',
            );

            /*
             * STEP 9: COMPANY SCHEDULES THE INTERVIEW.
             *
             * This uses the real OpportunityInterviewService:
             * - validates company/opportunity/application ownership
             * - creates OpportunityInterview as scheduled
             * - opens polymorphic Conversation for the interview
             * - adds company + student participants
             */
            /** @var OpportunityInterviewService $interviewService */
            $interviewService = app(OpportunityInterviewService::class);

            $interview = $interviewService->schedule(
                companyId: (int) $company->id,
                companyUserId: (int) $companyUser->id,
                opportunityId: (int) $opportunity->id,
                applicationId: (int) $application->id,
                data: [
                    'scheduled_at' => now()->addDay(),
                    'meeting_type' => 'online',
                    'meeting_link' => 'https://meet.example.com/jisr-complaint-interview-demo',
                    'location' => null,
                    'notes' => 'Demo interview for contextual complaint API testing.',
                ]
            );

            $this->assertStatus(
                actual: (string) $interview->status,
                expected: 'scheduled',
                label: 'Interview after scheduling',
            );

            /*
             * STEP 10: Verify the real interview Conversation.
             * ComplaintContextResolver depends on these participants.
             */
            $conversation = DB::table('conversations')
                ->where(
                    'conversationable_type',
                    $interview->getMorphClass()
                )
                ->where(
                    'conversationable_id',
                    $interview->id
                )
                ->first();

            if (! $conversation) {
                throw new \RuntimeException(
                    'Opportunity interview conversation was not created.'
                );
            }

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
                    'Interview conversation does not contain the expected company/student participants.'
                );
            }

            return [
                'student' => $student,
                'company_user' => $companyUser,
                'company' => $company,
                'opportunity' => $opportunity,
                'application' => $application,
                'interview' => $interview,
                'conversation_id' => (int) $conversation->id,
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
        $attributes = [
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'is_active' => true,
            'email_verified' => true,
        ];

        if ($isCompany) {
            $attributes['is_verified_by_admin'] = 'accepted';
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            $attributes
        );

        $roleModel = Role::findOrCreate($role, 'web');

        if (! $user->hasRole($role)) {
            $user->assignRole($roleModel);
        }

        return $user;
    }

    private function removePreviousDemoWorkflow(
        Company $company,
        User $student
    ): void {
        $opportunities = Opportunity::query()
            ->where('company_id', $company->id)
            ->where('title', self::OPPORTUNITY_TITLE)
            ->get();

        if ($opportunities->isEmpty()) {
            return;
        }

        $opportunityIds = $opportunities->pluck('id');

        $interviewIds = OpportunityInterview::query()
            ->whereIn('opportunity_id', $opportunityIds)
            ->where('student_user_id', $student->id)
            ->pluck('id');

        if ($interviewIds->isNotEmpty()) {
            $interviewMorphType = (new OpportunityInterview)
                ->getMorphClass();

            $conversationIds = DB::table('conversations')
                ->where('conversationable_type', $interviewMorphType)
                ->whereIn('conversationable_id', $interviewIds)
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

            /*
             * Remove only complaints created for the previous dedicated
             * interview demo context so rerunning remains deterministic.
             */
            if (
                Schema::hasTable('complaints')
                && Schema::hasColumn('complaints', 'context_type')
                && Schema::hasColumn('complaints', 'context_id')
            ) {
                DB::table('complaints')
                    ->where('context_type', 'opportunity_interview')
                    ->whereIn('context_id', $interviewIds)
                    ->delete();
            }
        }

        /*
         * Deleting the dedicated opportunity cascades to its applications
         * and interviews through their foreign keys.
         */
        Opportunity::query()
            ->whereIn('id', $opportunityIds)
            ->delete();
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

        /** @var Opportunity $opportunity */
        $opportunity = $result['opportunity'];

        /** @var Application $application */
        $application = $result['application'];

        /** @var OpportunityInterview $interview */
        $interview = $result['interview'];

        $conversationId = $result['conversation_id'];

        $this->command?->newLine();
        $this->command?->info(
            'Complaint Opportunity Interview real flow seeded successfully.'
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
            '  Opportunity ID: '.$opportunity->id
            .' | status='.$opportunity->status
        );
        $this->command?->line(
            '  Application ID: '.$application->id
            .' | status='.$application->status
        );
        $this->command?->line(
            '  Interview ID: '.$interview->id
            .' | status='.$interview->status
        );
        $this->command?->line(
            '  Conversation ID: '.$conversationId
        );

        $this->command?->line('');
        $this->command?->info(
            'Student -> Company complaint:'
        );
        $this->command?->line(
            '  POST /api/complaints'
        );
        $this->command?->line(
            '  context_type = opportunity_interview'
        );
        $this->command?->line(
            '  context_id = '.$interview->id
        );

        $this->command?->line('');
        $this->command?->info(
            'Company -> Student uses the same Interview ID.'
        );
        $this->command?->newLine();
    }
}
