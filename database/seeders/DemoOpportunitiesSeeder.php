<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoOpportunitiesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Find or create a company user
        $companyUser = User::firstOrCreate(
            ['email' => 'company@test.com'],
            [
                'name' => 'Tech Solutions Ltd',
                'password' => Hash::make('password'),
            ]
        );

        if (method_exists($companyUser, 'assignRole')) {
            $companyUser->assignRole('company');
        }

        // 2. Find or create the company profile
        $company = $companyUser->companies()->first();
        if (! $company) {
            $company = Company::create([
                'industry' => 'Software Development',
                'location' => 'Amman, Jordan',
                'website' => 'https://techsolutions.example.com',
            ]);
            $companyUser->companies()->attach($company->id, [
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Find the matching skills (Python, Flask, SQL, Git)
        $skills = DB::table('skills')
            ->whereIn('name', ['Python', 'Flask', 'SQL', 'Git'])
            ->get();

        if ($skills->isEmpty()) {
            throw new RuntimeException('No demo skills found in the database. Please run SprintOneFoundationSeeder first.');
        }

        // 4. Create a Job Opportunity
        $jobOpportunity = Opportunity::updateOrCreate(
            [
                'company_id' => $company->id,
                'title' => 'Junior Backend Developer (Python/Flask)',
            ],
            [
                'description' => 'We are looking for a Junior Backend Developer to design and develop scalable RESTful APIs using Python and Flask. You will collaborate with the team and work with SQL databases, using Git for version control.',
                'type' => 'job',
                'location' => 'Amman, Jordan (On-site)',
                'salary_min' => 500.00,
                'salary_max' => 800.00,
                'status' => 'published',
                'deadline' => now()->addDays(30),
                'posted_at' => now(),
            ]
        );

        // 5. Create an Internship Opportunity
        $internOpportunity = Opportunity::updateOrCreate(
            [
                'company_id' => $company->id,
                'title' => 'Backend Development Intern (Python)',
            ],
            [
                'description' => 'Great opportunity for students to gain hands-on experience in backend web development. You will learn Flask, write SQL queries, and manage code using Git under mentor supervision.',
                'type' => 'internship',
                'location' => 'Remote',
                'salary_min' => 150.00,
                'salary_max' => 250.00,
                'status' => 'published',
                'deadline' => now()->addDays(15),
                'posted_at' => now(),
            ]
        );

        // 6. Attach skills to the opportunities
        $opportunities = [$jobOpportunity, $internOpportunity];

        foreach ($opportunities as $opportunity) {
            foreach ($skills as $skill) {
                // Set custom levels/weights for each skill
                $requiredLevel = 2; // default
                $weight = 1.00;
                $mandatory = true;

                if ($skill->name === 'Python') {
                    $requiredLevel = 3;
                    $weight = 1.00;
                } elseif ($skill->name === 'Flask') {
                    $requiredLevel = 2;
                    $weight = 0.90;
                } elseif ($skill->name === 'SQL') {
                    $requiredLevel = 2;
                    $weight = 0.85;
                } elseif ($skill->name === 'Git') {
                    $requiredLevel = 2;
                    $weight = 0.70;
                    $mandatory = false; // git is optional/nice to have
                }

                DB::table('opportunity_skills')->updateOrInsert(
                    [
                        'opportunity_id' => $opportunity->id,
                        'skill_id' => $skill->id,
                    ],
                    [
                        'required_level' => $requiredLevel,
                        'mandatory' => $mandatory,
                        'weight' => $weight,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $this->command->info('Demo opportunities with matching student skills seeded successfully.');
    }
}
