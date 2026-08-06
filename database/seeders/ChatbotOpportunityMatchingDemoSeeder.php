<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChatbotOpportunityMatchingDemoSeeder extends Seeder
{
    private const COMPANY_WEBSITE = 'https://chatbot-demo.local';
    private const TITLE_PREFIX = '[Chatbot Demo] ';

    public function run(): void
    {
        $this->ensureSafeEnvironment();

        $studentId = (int) env('CHATBOT_DEMO_STUDENT_ID', 2);

        if (User::query()->find($studentId) === null) {
            throw new RuntimeException("Student user {$studentId} was not found.");
        }

        if (! UserSkill::query()->where('UserId', $studentId)->exists()) {
            throw new RuntimeException(
                'The demo student has no skills. Run ChatbotSkillsMarketDemoSeeder first.'
            );
        }

        $skills = Skill::query()
            ->whereIn('name', ['Python', 'REST API', 'SQL', 'Flask', 'Git', 'Laravel'])
            ->get()
            ->keyBy('name');

        $requiredNames = ['Python', 'REST API', 'SQL', 'Flask', 'Git', 'Laravel'];
        $missing = collect($requiredNames)->reject(fn (string $name) => $skills->has($name));

        if ($missing->isNotEmpty()) {
            throw new RuntimeException(
                'Missing required demo skills: ' . $missing->implode(', ')
            );
        }

        DB::transaction(function () use ($skills): void {
            $company = Company::query()->updateOrCreate(
                ['website' => self::COMPANY_WEBSITE],
                [
                    'industry' => 'Software Development',
                    'location' => 'Remote',
                ],
            );

            $this->upsertOpportunity(
                companyId: (int) $company->id,
                title: self::TITLE_PREFIX . 'Flask API Internship',
                description: 'Build REST APIs with Python, Flask and SQL.',
                type: 'internship',
                location: 'Remote',
                skills: [
                    $skills['Python']->id => ['required_level' => 2, 'mandatory' => true, 'weight' => 1.50],
                    $skills['Flask']->id => ['required_level' => 1, 'mandatory' => true, 'weight' => 1.25],
                    $skills['REST API']->id => ['required_level' => 2, 'mandatory' => true, 'weight' => 1.25],
                    $skills['SQL']->id => ['required_level' => 2, 'mandatory' => false, 'weight' => 1.00],
                ],
            );

            $this->upsertOpportunity(
                companyId: (int) $company->id,
                title: self::TITLE_PREFIX . 'Python Backend Trainee',
                description: 'Backend trainee opportunity using Python, REST APIs and SQL.',
                type: 'internship',
                location: 'Hybrid',
                skills: [
                    $skills['Python']->id => ['required_level' => 3, 'mandatory' => true, 'weight' => 1.50],
                    $skills['REST API']->id => ['required_level' => 2, 'mandatory' => true, 'weight' => 1.25],
                    $skills['SQL']->id => ['required_level' => 2, 'mandatory' => true, 'weight' => 1.25],
                    $skills['Git']->id => ['required_level' => 1, 'mandatory' => false, 'weight' => 0.50],
                ],
            );

            // This record should not be recommended because Laravel is mandatory
            // and the demo student does not own it.
            $this->upsertOpportunity(
                companyId: (int) $company->id,
                title: self::TITLE_PREFIX . 'Laravel Backend Internship',
                description: 'Laravel backend internship with REST API and Git requirements.',
                type: 'internship',
                location: 'On-site',
                skills: [
                    $skills['Laravel']->id => ['required_level' => 2, 'mandatory' => true, 'weight' => 1.50],
                    $skills['REST API']->id => ['required_level' => 2, 'mandatory' => false, 'weight' => 1.00],
                    $skills['Git']->id => ['required_level' => 1, 'mandatory' => true, 'weight' => 1.00],
                ],
            );
        });

        $this->command?->info('Opportunity matching demo data created successfully.');
        $this->command?->line("Student ID used for matching: {$studentId}");
        $this->command?->warn(
            'Remove only these demo opportunities later with: ' .
            'php artisan db:seed --class=ChatbotOpportunityMatchingDemoCleanupSeeder'
        );
    }

    private function upsertOpportunity(
        int $companyId,
        string $title,
        string $description,
        string $type,
        string $location,
        array $skills,
    ): void {
        $opportunity = Opportunity::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'title' => $title,
            ],
            [
                'description' => $description,
                'type' => $type,
                'location' => $location,
                'salary_min' => null,
                'salary_max' => null,
                'status' => 'published',
                'deadline' => now()->addDays(30),
                'posted_at' => now(),
            ],
        );

        $opportunity->skills()->sync($skills);
    }

    private function ensureSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'ChatbotOpportunityMatchingDemoSeeder is allowed only in local or testing environments.'
            );
        }
    }
}
