<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoCompanyTasksSeeder extends Seeder
{
    public function run(): void
    {
        $company = DB::table('companies')->first();

        if (! $company) {
            throw new RuntimeException('No company found. Please create a company before running DemoCompanyTasksSeeder.');
        }

        $skillIds = DB::table('skills')
            ->whereIn('name', ['Python', 'Flask', 'SQL', 'Git'])
            ->pluck('id');

        if ($skillIds->isEmpty()) {
            throw new RuntimeException('No demo skills found. Run SprintOneFoundationSeeder first.');
        }

        $taskId = DB::table('company_tasks')->insertGetId([
            'company_id' => $company->id,
            'title' => 'Build Flask REST API',
            'description' => 'Create a clean REST API using Flask, SQL, Git, and proper API structure.',
            'difficulty_level' => 'intermediate',
            'duration_days' => 5,
            'deadline' => now()->addDays(10),
            'max_applicants' => 20,
            'max_accepted_students' => 2,
            'deliverables' => json_encode([
                'GitHub repository link',
                'API documentation',
                'Database schema',
            ]),
            'acceptance_criteria' => json_encode([
                'Use Flask clean structure',
                'Use SQL database correctly',
                'Push code to GitHub',
            ]),
            'submission_type' => 'github_link',
            'status' => 'published',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($skillIds as $skillId) {
            DB::table('company_task_skills')->insert([
                'company_task_id' => $taskId,
                'skill_id' => $skillId,
                'required_level' => 3,
                'weight' => 1.00,
                'mandatory' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Demo task created for the first company successfully.');
    }
}
