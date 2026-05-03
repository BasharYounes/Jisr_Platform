<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SkillAliasSeeder extends Seeder
{
    public function run(): void
    {
        $aliases = [
            'Python' => ['python3', 'py'],
            'Flask'  => ['flask framework'],
            'SQL'    => ['mysql', 'postgresql', 'postgres', 'sqlite', 'sql database'],
            'Git'    => ['github', 'git version control'],
        ];

        foreach ($aliases as $skillName => $skillAliases) {
            $skillId = DB::table('skills')->where('name', $skillName)->value('id');

            if (!$skillId) {
                continue;
            }

            foreach ($skillAliases as $alias) {
                DB::table('skill_aliases')->updateOrInsert(
                    ['Alias' => $alias],
                    [
                        'SkillID' => $skillId,
                        'LanguageCode' => 'en',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
