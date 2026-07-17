<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpertQuestionCatalogSeeder extends Seeder
{
    /*
     * The order below is the canonical order in BackendQuestionBankSeeder.
     * Topic is an existing question_bank column and is used as the stable key.
     */
    public const TOPICS_BY_SKILL = [
        'Python' => [
            'variables',
            'conditional_if',
            'loops_for_while',
            'list_vs_tuple',
            'file_reading',
            'functions_even_numbers',
            'exception_handling',
            'oop_class_object',
            'unit_testing',
            'decorators',
            'generators',
            'collections_performance',
            'asyncio',
            'packaging',
            'api_versioning',
        ],
        'Flask' => [
            'flask_hello_world',
            'flask_routing',
            'flask_local_development',
            'flask_post_json',
            'flask_jsonify_vs_string',
            'flask_render_template_redirect',
            'flask_blueprints',
            'flask_error_handling',
            'flask_input_validation',
            'flask_environment_configuration',
            'flask_api_authentication',
            'flask_production_readiness',
            'flask_scalability',
            'flask_testing_organization',
            'flask_layered_architecture',
        ],
        'SQL' => [
            'sql_select_all',
            'sql_where_filtering',
            'sql_select_columns',
            'sql_joins',
            'sql_where_having',
            'sql_group_by_count',
            'sql_subqueries',
            'sql_normalization',
            'sql_and_or_logic',
            'sql_indexes',
            'sql_query_performance_diagnosis',
            'sql_query_inefficiencies',
            'sql_schema_design',
            'sql_query_optimization_large_system',
            'sql_design_performance_scalability_balance',
        ],
        'Git' => [
            'git_general_purpose',
            'git_add_vs_commit',
            'git_push',
            'git_branches',
            'git_merge',
            'git_merge_conflict',
            'git_pull_request',
            'git_merge_vs_rebase',
            'git_clean_history',
            'git_complex_merge_conflicts',
            'git_branch_workflow',
            'git_force_push_risk',
            'git_backend_team_strategy',
            'git_history_recovery',
            'git_team_professional_practices',
        ],
    ];

    private const CAREER_PATH_NAME = 'Backend Developer';

    public function run(): void
    {
        DB::transaction(function (): void {
            $careerPathId = DB::table('career_paths')
                ->where('Name', self::CAREER_PATH_NAME)
                ->value('CareerPathID');

            if (! $careerPathId) {
                throw new RuntimeException(
                    'Backend Developer career path was not found.'
                );
            }

            foreach (self::TOPICS_BY_SKILL as $skillName => $topics) {
                $skillId = DB::table('skills')
                    ->where('name', $skillName)
                    ->value('id');

                if (! $skillId) {
                    throw new RuntimeException(
                        "Skill {$skillName} was not found."
                    );
                }

                $pendingQuestions = DB::table('question_bank')
                    ->where('CareerPathID', $careerPathId)
                    ->where('SkillID', $skillId)
                    ->where('QuestionType', 'open_text')
                    ->whereNull('Topic')
                    ->orderBy('QuestionID')
                    ->get();

                if ($pendingQuestions->isEmpty()) {
                    $this->assertSkillCatalogIsComplete(
                        careerPathId: (int) $careerPathId,
                        skillId: (int) $skillId,
                        skillName: $skillName,
                        topics: $topics,
                    );

                    continue;
                }

                if ($pendingQuestions->count() !== count($topics)) {
                    throw new RuntimeException(
                        'Expected ' . count($topics)
                        . " unclassified {$skillName} questions, found "
                        . $pendingQuestions->count()
                        . '. Check BackendQuestionBankSeeder before continuing.'
                    );
                }

                foreach ($pendingQuestions as $index => $question) {
                    $topic = $topics[$index];

                    $existingQuestionId = DB::table('question_bank')
                        ->where('CareerPathID', $careerPathId)
                        ->where('SkillID', $skillId)
                        ->where('Topic', $topic)
                        ->where('QuestionID', '<>', $question->QuestionID)
                        ->value('QuestionID');

                    if ($existingQuestionId) {
                        $this->discardFreshDuplicateQuestion(
                            questionId: (int) $question->QuestionID,
                            topic: $topic,
                        );

                        continue;
                    }

                    DB::table('question_bank')
                        ->where('QuestionID', $question->QuestionID)
                        ->update([
                            'Topic' => $topic,
                            'updated_at' => now(),
                        ]);
                }

                $this->assertSkillCatalogIsComplete(
                    careerPathId: (int) $careerPathId,
                    skillId: (int) $skillId,
                    skillName: $skillName,
                    topics: $topics,
                );
            }
        });

        if ($this->command) {
            $this->command->info(
                'Expert question catalog topics were synchronized successfully.'
            );
        }
    }

    private function assertSkillCatalogIsComplete(
        int $careerPathId,
        int $skillId,
        string $skillName,
        array $topics,
    ): void {
        $configuredCount = DB::table('question_bank')
            ->where('CareerPathID', $careerPathId)
            ->where('SkillID', $skillId)
            ->whereIn('Topic', $topics)
            ->count();

        if ($configuredCount !== count($topics)) {
            throw new RuntimeException(
                "Expert topic catalog is incomplete for {$skillName}: "
                . 'expected ' . count($topics)
                . ", found {$configuredCount}."
            );
        }
    }

    private function discardFreshDuplicateQuestion(
        int $questionId,
        string $topic,
    ): void {
        $hasAttempts = DB::table('assessment_question_attempts')
            ->where('QuestionID', $questionId)
            ->exists();

        $hasRuleSets = DB::table('assessment_rule_sets')
            ->where('QuestionID', $questionId)
            ->exists();

        if ($hasAttempts || $hasRuleSets) {
            throw new RuntimeException(
                "Cannot discard duplicate question {$questionId} for topic {$topic} "
                . 'because it already has attempts or Expert Rules.'
            );
        }

        DB::table('question_rubrics')
            ->where('QuestionID', $questionId)
            ->delete();

        DB::table('question_bank')
            ->where('QuestionID', $questionId)
            ->delete();
    }
}
