<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoStudentSkillsSeeder extends Seeder
{
    public function run(): void
    {
        $student = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'student')
            ->select('users.id')
            ->skip(3)
            ->first();

        if (! $student) {
            throw new RuntimeException('No student user found. Please create a student before running DemoStudentSkillsSeeder.');
        }

        $skillIds = DB::table('skills')
            ->whereIn('name', ['Python', 'Flask', 'SQL', 'Git'])
            ->pluck('id');

        if ($skillIds->isEmpty()) {
            throw new RuntimeException('No demo skills found. Run SprintOneFoundationSeeder first.');
        }

        foreach ($skillIds as $skillId) {
            DB::table('user_skills')->updateOrInsert(
                [
                    'UserId' => $student->id,
                    'SkillId' => $skillId,
                ],
                [
                    'ProficiencyLevel' => 4,
                    'ConfidenceScore' => 0.80,
                    'Source' => 'manual',
                    'Verified' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Demo skills assigned to the first student successfully.');
    }
}