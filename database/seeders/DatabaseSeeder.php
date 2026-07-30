<?php

namespace Database\Seeders;

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

            DemoOpportunitiesSeeder::class,
            PointRulesSeeder::class,
            FullSkillsSeeder::class,
            DemoCompanyTaskWorkflowSeeder::class,
            DemoTaskConversationMessagesSeeder::class,

            // Canonical Backend questions, then stable Expert-System catalog.
            BackendQuestionBankSeeder::class,
            ExpertQuestionCatalogSeeder::class,

            // Expert Rules for all 60 Backend open-text questions.
            PythonVariableValueExpertRuleSeeder::class,
            PythonFundamentalsExpertRulesSeeder::class,
            PythonAdvancedExpertRulesSeeder::class,
            FlaskExpertRulesSeeder::class,
            SqlExpertRulesSeeder::class,
            GitExpertRulesSeeder::class,

            // Runs only after the 60 Rule Sets pass structural validation.
            ExpertRulesActivationSeeder::class,

            SupervisorWorkflowTestSeeder::class,
        ]);
    }
}
