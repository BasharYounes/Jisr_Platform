<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MatchingTestSeeder extends Seeder
{
    public function run(): void
    {
        // Skills
        DB::table('Skill')->insert([
            ['SkillID' => 1, 'Name' => 'Laravel'],
            ['SkillID' => 2, 'Name' => 'SQL'],
            ['SkillID' => 3, 'Name' => 'Docker'],
        ]);

        // Opportunity
        DB::table('Opportunity')->insert([
            'OpportunityID' => 1,
            'Title' => 'Junior Backend Developer',
        ]);

        DB::table('OpportunitySkill')->insert([
            [
                'OpportunityId' => 1,
                'SkillId' => 1,
                'Weight' => 5,
                'Mandatory' => true,
                'RequiredLevel' => 3,
            ],
            [
                'OpportunityId' => 1,
                'SkillId' => 2,
                'Weight' => 5,
                'Mandatory' => true,
                'RequiredLevel' => 3,
            ],
            [
                'OpportunityId' => 1,
                'SkillId' => 3,
                'Weight' => 2,
                'Mandatory' => false,
                'RequiredLevel' => 1,
            ],
        ]);
    }
}
