<?php

namespace Database\Seeders;

use App\Enums\MentorApplicationSource;
use App\Enums\MentorApplicationStatus;
use App\Models\Company;
use App\Models\MentorProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ComplaintApprovedExternalMentorSeeder extends Seeder
{
    private const COMPANY_USER_EMAIL = 'complaint.task.company@jisr.test';

    private const COMPANY_WEBSITE = 'https://complaint-task-demo.jisr.test';

    private const MENTOR_EMAIL = 'external.mentor.complaint@jisr.test';

    public function run(): void
    {
        $result = DB::transaction(function (): array {
            /*
             * STEP 1: Reuse/create the deterministic demo company user.
             * This user submits the nomination, but the mentor themself
             * intentionally DOES NOT have a Jisr User account.
             */
            $companyUser = User::query()->firstOrCreate(
                ['email' => self::COMPANY_USER_EMAIL],
                [
                    'name' => 'Complaint Flow Demo Company',
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified' => true,
                    'is_verified_by_admin' => 'accepted',
                ]
            );

            $companyRole = Role::findOrCreate('company', 'web');

            if (! $companyUser->hasRole('company')) {
                $companyUser->assignRole($companyRole);
            }

            /*
             * STEP 2: Reuse/create the dedicated demo company.
             */
            $company = Company::query()->updateOrCreate(
                ['website' => self::COMPANY_WEBSITE],
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
             * STEP 3: Resolve Laravel skill so this external mentor appears
             * as relevant/recommended to the complaint demo student.
             */
            $laravel = Skill::query()
                ->where('name', 'Laravel')
                ->first();

            if (! $laravel) {
                $laravel = Skill::query()->create([
                    'name' => 'Laravel',
                    'category' => 'Framework',
                    'normalized_name' => 'laravel',
                ]);
            }

            /*
             * STEP 4: Create/update an APPROVED EXTERNAL mentor profile.
             *
             * Critical point:
             *   user_id = null
             *
             * This intentionally proves that contextual complaints target
             * MentorProfile, not User.
             *
             * We model it as a company nomination because an external mentor
             * without a Jisr account cannot submit a self-application.
             */
            $mentor = MentorProfile::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email' => self::MENTOR_EMAIL,
                ],
                [
                    'user_id' => null,
                    'submitted_by_user_id' => $companyUser->id,
                    'source' => MentorApplicationSource::CompanyNomination,
                    'status' => MentorApplicationStatus::Approved,

                    'full_name' => 'External Laravel Mentor',
                    'whatsapp_number' => '+963900001234',

                    'specialization' => 'backend',
                    'professional_title' => 'Senior Laravel Backend Engineer',
                    'expertise' => 'Laravel, REST APIs, backend architecture, database design, API security, and code review.',
                    'bio' => 'External mentor created specifically for testing approved mentor discovery and contextual complaints.',

                    'linkedin_url' => 'https://www.linkedin.com/in/external-laravel-mentor-demo',
                    'github_or_portfolio_url' => 'https://github.com/example/external-laravel-mentor',
                    'cv_path' => null,

                    'mentoring_topics' => [
                        'Laravel backend development',
                        'REST API design',
                        'Database architecture',
                        'Backend code review',
                    ],

                    'reviewed_by' => null,
                    'reviewed_at' => now(),
                    'rejection_reason' => null,

                    'is_volunteer' => true,
                    'hourly_rate' => null,
                ]
            );

            /*
             * STEP 5: Attach Laravel skill.
             */
            $mentor->skills()->syncWithoutDetaching([
                $laravel->id => [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $mentor->refresh();
            $mentor->load('skills:id,name,category');

            /*
             * STEP 6: Defensive assertions.
             */
            if ($mentor->user_id !== null) {
                throw new \RuntimeException(
                    'Demo mentor must remain external: user_id must be null.'
                );
            }

            if ($mentor->status !== MentorApplicationStatus::Approved) {
                throw new \RuntimeException(
                    'Demo mentor must have approved status.'
                );
            }

            return [
                'mentor' => $mentor,
                'company' => $company,
                'company_user' => $companyUser,
                'skill' => $laravel,
            ];
        });

        $this->printSummary($result);
    }

    private function printSummary(array $result): void
    {
        /** @var MentorProfile $mentor */
        $mentor = $result['mentor'];

        /** @var Company $company */
        $company = $result['company'];

        /** @var User $companyUser */
        $companyUser = $result['company_user'];

        /** @var Skill $skill */
        $skill = $result['skill'];

        $this->command?->newLine();
        $this->command?->info(
            'Approved external mentor seeded successfully.'
        );

        $this->command?->line('');
        $this->command?->line('MENTOR PROFILE');
        $this->command?->line('  MentorProfile ID: '.$mentor->id);
        $this->command?->line('  Full name: '.$mentor->full_name);
        $this->command?->line('  Email: '.$mentor->email);
        $this->command?->line('  Status: '.$mentor->status->value);
        $this->command?->line('  Source: '.$mentor->source->value);
        $this->command?->line(
            '  Internal User ID: '.($mentor->user_id ?? 'NULL (external mentor)')
        );
        $this->command?->line('  Skill: '.$skill->name);

        $this->command?->line('');
        $this->command?->line('NOMINATING COMPANY');
        $this->command?->line('  Company ID: '.$company->id);
        $this->command?->line('  Company User ID: '.$companyUser->id);
        $this->command?->line(
            '  Company User Email: '.$companyUser->email
        );

        $this->command?->line('');
        $this->command?->info(
            'Now test Student Mentor Discovery:'
        );
        $this->command?->line(
            '  GET /api/student/mentors'
        );

        $this->command?->line('');
        $this->command?->info(
            'Then submit the contextual complaint:'
        );
        $this->command?->line(
            '  POST /api/complaints'
        );
        $this->command?->line(
            '  context_type = mentor_profile'
        );
        $this->command?->line(
            '  context_id = '.$mentor->id
        );

        $this->command?->newLine();
    }
}
