<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSkillSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 3;

        $skills = [
            [
                'SkillId' => 1, // Python
                'ProficiencyLevel' => 4,
                'ConfidenceScore' => 0.90,
                'Source' => 'Manual',
                'Verified' => true,
            ],
            // [
            //     'SkillId' => 3, // SQL
            //     'ProficiencyLevel' => 3,
            //     'ConfidenceScore' => 0.80,
            //     'Source' => 'Manual',
            //     'Verified' => true,
            // ],
            // [
            //     'SkillId' => 4, // Git
            //     'ProficiencyLevel' => 3,
            //     'ConfidenceScore' => 0.75,
            //     'Source' => 'Manual',
            //     'Verified' => true,
            // ],
        ];

        foreach ($skills as $skill) {
            DB::table('user_skills')->updateOrInsert(
                [
                    'UserId' => $userId,
                    'SkillId' => $skill['SkillId'],
                ],
                [
                    'ProficiencyLevel' => $skill['ProficiencyLevel'],
                    'ConfidenceScore' => $skill['ConfidenceScore'],
                    'Source' => $skill['Source'],
                    'Verified' => $skill['Verified'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
