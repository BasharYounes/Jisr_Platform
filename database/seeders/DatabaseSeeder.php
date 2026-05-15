<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

            $this->call([
                RoleSeeder::class,
                AdminSeeder::class,
                CareerPathSeeder::class,
                SprintOneFoundationSeeder::class,
                SkillAliasSeeder::class,
                SkillLevelDefinitionSeeder::class,
                BackendQuestionBankSeeder::class,
                SupervisorWorkflowTestSeeder::class,
            ]);



    }
}
