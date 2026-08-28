<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GraduationDemoExpertSystemBootstrapSeeder extends Seeder
{
    private const CAREER_PATH_NAME = 'Backend Developer';

    private const EXPECTED_SKILLS = [
        'Python',
        'Flask',
        'SQL',
        'Git',
    ];

    private const EXPECTED_QUESTIONS_PER_SKILL = 15;

    private const EXPECTED_TOTAL_QUESTIONS = 60;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'This bootstrap seeder is allowed only in local/testing environments.'
            );
        }

        $careerPathId = DB::table('career_paths')
            ->where('Name', self::CAREER_PATH_NAME)
            ->value('CareerPathID');

        if (! $careerPathId) {
            throw new RuntimeException(
                'Backend Developer career path was not found. '
                .'Run CareerPathSeeder and SprintOneFoundationSeeder first.'
            );
        }

        $skillIds = [];

        foreach (self::EXPECTED_SKILLS as $skillName) {
            $skillId = DB::table('skills')
                ->where('name', $skillName)
                ->value('id');

            if (! $skillId) {
                throw new RuntimeException(
                    "Required skill {$skillName} was not found. "
                    .'Run SprintOneFoundationSeeder first.'
                );
            }

            $skillIds[$skillName] = (int) $skillId;
        }

        $existingQuestionCount = DB::table('question_bank')
            ->where('CareerPathID', $careerPathId)
            ->whereIn('SkillID', array_values($skillIds))
            ->where('QuestionType', 'open_text')
            ->count();

        $readyCount = DB::table('question_bank')
            ->where('CareerPathID', $careerPathId)
            ->whereIn('SkillID', array_values($skillIds))
            ->where('QuestionType', 'open_text')
            ->where('EvaluationEngine', 'expert_rules')
            ->where('IsExpertReady', true)
            ->count();

        if (
            $existingQuestionCount === self::EXPECTED_TOTAL_QUESTIONS
            && $readyCount === self::EXPECTED_TOTAL_QUESTIONS
        ) {
            $this->command?->info(
                'Expert System is already fully seeded and activated (60/60 questions).'
            );

            $this->printVerification(
                careerPathId: (int) $careerPathId,
                skillIds: $skillIds
            );

            return;
        }

        /*
         * Safe states:
         * 0 questions  -> create the canonical 60-question bank.
         * 60 questions -> continue configuring topics/rules/activation.
         *
         * Any other count means the local database is partially populated.
         * We stop instead of duplicating or silently mutating uncertain data.
         */
        if (! in_array(
            $existingQuestionCount,
            [0, self::EXPECTED_TOTAL_QUESTIONS],
            true
        )) {
            throw new RuntimeException(
                'Backend Expert System question bank is partially populated '
                ."({$existingQuestionCount}/".self::EXPECTED_TOTAL_QUESTIONS.'). '
                .'Refusing to create duplicates. Restore/clean the assessment '
                .'question data before running this bootstrap seeder.'
            );
        }

        if ($existingQuestionCount === 0) {
            $this->command?->info('1/9 Seeding canonical Backend question bank...');
            $this->call(BackendQuestionBankSeeder::class);
        } else {
            $this->command?->info(
                '1/9 Canonical Backend question bank already contains 60 questions; reusing it.'
            );
        }

        $this->command?->info('2/9 Assigning stable Expert System topics...');
        $this->call(ExpertQuestionCatalogSeeder::class);

        $this->command?->info('3/9 Seeding Python variable/value Expert Rule...');
        $this->call(PythonVariableValueExpertRuleSeeder::class);

        $this->command?->info('4/9 Seeding Python fundamentals Expert Rules...');
        $this->call(PythonFundamentalsExpertRulesSeeder::class);

        $this->command?->info('5/9 Seeding Python advanced Expert Rules...');
        $this->call(PythonAdvancedExpertRulesSeeder::class);

        $this->command?->info('6/9 Seeding Flask Expert Rules...');
        $this->call(FlaskExpertRulesSeeder::class);

        $this->command?->info('7/9 Seeding SQL Expert Rules...');
        $this->call(SqlExpertRulesSeeder::class);

        $this->command?->info('8/9 Seeding Git Expert Rules...');
        $this->call(GitExpertRulesSeeder::class);

        $this->command?->info('9/9 Activating validated Expert System questions...');
        $this->call(ExpertRulesActivationSeeder::class);

        $this->verifyFinalState(
            careerPathId: (int) $careerPathId,
            skillIds: $skillIds
        );

        $this->command?->newLine();
        $this->command?->info(
            'Backend Expert System bootstrap completed successfully.'
        );

        $this->printVerification(
            careerPathId: (int) $careerPathId,
            skillIds: $skillIds
        );
    }

    /**
     * @param  array<string, int>  $skillIds
     */
    private function verifyFinalState(int $careerPathId, array $skillIds): void
    {
        $totalReady = DB::table('question_bank')
            ->where('CareerPathID', $careerPathId)
            ->whereIn('SkillID', array_values($skillIds))
            ->where('QuestionType', 'open_text')
            ->where('IsActive', true)
            ->where('EvaluationEngine', 'expert_rules')
            ->where('RuleSetVersion', 'v1')
            ->where('IsExpertReady', true)
            ->count();

        if ($totalReady !== self::EXPECTED_TOTAL_QUESTIONS) {
            throw new RuntimeException(
                'Expert System activation verification failed: expected '
                .self::EXPECTED_TOTAL_QUESTIONS
                ." ready questions, found {$totalReady}."
            );
        }

        foreach ($skillIds as $skillName => $skillId) {
            $count = DB::table('question_bank')
                ->where('CareerPathID', $careerPathId)
                ->where('SkillID', $skillId)
                ->where('QuestionType', 'open_text')
                ->where('IsActive', true)
                ->where('EvaluationEngine', 'expert_rules')
                ->where('RuleSetVersion', 'v1')
                ->where('IsExpertReady', true)
                ->whereNotNull('Topic')
                ->count();

            if ($count !== self::EXPECTED_QUESTIONS_PER_SKILL) {
                throw new RuntimeException(
                    "{$skillName} Expert System verification failed: expected "
                    .self::EXPECTED_QUESTIONS_PER_SKILL
                    ." ready questions, found {$count}."
                );
            }
        }
    }

    /**
     * @param  array<string, int>  $skillIds
     */
    private function printVerification(
        int $careerPathId,
        array $skillIds
    ): void {
        $rows = [];

        foreach ($skillIds as $skillName => $skillId) {
            $total = DB::table('question_bank')
                ->where('CareerPathID', $careerPathId)
                ->where('SkillID', $skillId)
                ->count();

            $withTopic = DB::table('question_bank')
                ->where('CareerPathID', $careerPathId)
                ->where('SkillID', $skillId)
                ->whereNotNull('Topic')
                ->count();

            $expertReady = DB::table('question_bank')
                ->where('CareerPathID', $careerPathId)
                ->where('SkillID', $skillId)
                ->where('IsActive', true)
                ->where('EvaluationEngine', 'expert_rules')
                ->where('RuleSetVersion', 'v1')
                ->where('IsExpertReady', true)
                ->count();

            $rows[] = [
                $skillName,
                $total,
                $withTopic,
                $expertReady,
            ];
        }

        $this->command?->table(
            ['Skill', 'Questions', 'Topics', 'Expert Ready'],
            $rows
        );
    }
}
