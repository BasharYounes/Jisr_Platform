<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SprintOneFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            ['name' => 'Python', 'category' => 'Programming Language', 'normalized_name' => 'python'],
            ['name' => 'Flask', 'category' => 'Framework', 'normalized_name' => 'flask'],
            ['name' => 'SQL', 'category' => 'Database', 'normalized_name' => 'sql'],
            ['name' => 'Git', 'category' => 'Version Control', 'normalized_name' => 'git'],
        ];

        foreach ($skills as $skill) {
            DB::table('skills')->updateOrInsert(
                ['name' => $skill['name']],
                [
                    'category' => $skill['category'],
                    'normalized_name' => $skill['normalized_name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $careerPathId = DB::table('career_paths')
            ->where('Name', 'Backend Developer')
            ->value('CareerPathID');

        $pathSkills = [
            ['name' => 'Python', 'required_level' => 3.0, 'weight' => 1.00, 'is_core' => true],
            ['name' => 'Flask',  'required_level' => 2.5, 'weight' => 0.90, 'is_core' => true],
            ['name' => 'SQL',    'required_level' => 2.5, 'weight' => 0.95, 'is_core' => true],
            ['name' => 'Git',    'required_level' => 2.0, 'weight' => 0.80, 'is_core' => true],
        ];

        if (!$careerPathId) {
            return;
        }

        foreach ($pathSkills as $item) {
            $skillId = DB::table('skills')->where('name', $item['name'])->value('id');

            if (!$skillId) {
                continue;
            }

            DB::table('career_path_skills')->updateOrInsert(
                [
                    'CareerPathID' => $careerPathId,
                    'SkillID' => $skillId,
                ],
                [
                    'RequiredLevel' => $item['required_level'],
                    'Weight' => $item['weight'],
                    'IsCore' => $item['is_core'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
