<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MatchingTestSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $companyId = DB::table('companies')->insertGetId([
            'industry' => 'Software',
            'location' => 'Damascus',
            'website' => 'https://example.test',
            'documentation_file' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $laravelId = DB::table('skills')->insertGetId([
            'name' => 'Laravel',
            'category' => 'Framework',
            'normalized_name' => 'laravel',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sqlId = DB::table('skills')->insertGetId([
            'name' => 'SQL',
            'category' => 'Database',
            'normalized_name' => 'sql',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $dockerId = DB::table('skills')->insertGetId([
            'name' => 'Docker',
            'category' => 'DevOps',
            'normalized_name' => 'docker',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $opportunityId = DB::table('opportunities')->insertGetId([
            'company_id' => $companyId,
            'title' => 'Junior Backend Developer - Matching Test',
            'description' => 'Matching engine test opportunity.',
            'type' => 'job',
            'location' => 'Damascus',
            'salary_min' => null,
            'salary_max' => null,
            'status' => 'published',
            'deadline' => now()->addWeek(),
            'posted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('opportunity_skills')->insert([
            [
                'opportunity_id' => $opportunityId,
                'skill_id' => $laravelId,
                'required_level' => 3,
                'mandatory' => true,
                'weight' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'opportunity_id' => $opportunityId,
                'skill_id' => $sqlId,
                'required_level' => 3,
                'mandatory' => true,
                'weight' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'opportunity_id' => $opportunityId,
                'skill_id' => $dockerId,
                'required_level' => 1,
                'mandatory' => false,
                'weight' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
