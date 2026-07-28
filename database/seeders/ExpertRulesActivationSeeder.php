<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpertRulesActivationSeeder extends Seeder
{
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

            $readyQuestionIds = [];

            foreach (ExpertQuestionCatalogSeeder::TOPICS_BY_SKILL as $skillName => $topics) {
                $skillId = DB::table('skills')
                    ->where('name', $skillName)
                    ->value('id');

                if (! $skillId) {
                    throw new RuntimeException(
                        "Skill {$skillName} was not found."
                    );
                }

                $questions = DB::table('question_bank')
                    ->where('CareerPathID', $careerPathId)
                    ->where('SkillID', $skillId)
                    ->whereIn('Topic', $topics)
                    ->orderBy('QuestionID')
                    ->get();

                if ($questions->count() !== count($topics)) {
                    throw new RuntimeException(
                        'Activation expected '.count($topics)
                        ." {$skillName} questions, found "
                        .$questions->count().'.'
                    );
                }

                foreach ($questions as $question) {
                    if (strtolower((string) $question->QuestionType) !== 'open_text') {
                        throw new RuntimeException(
                            "Question {$question->QuestionID} must be open_text."
                        );
                    }

                    if ((string) $question->EvaluationEngine !== 'expert_rules') {
                        throw new RuntimeException(
                            "Question {$question->QuestionID} is not configured for expert_rules."
                        );
                    }

                    if ((string) $question->RuleSetVersion !== 'v1') {
                        throw new RuntimeException(
                            "Question {$question->QuestionID} does not use RuleSetVersion v1."
                        );
                    }

                    $ruleSet = DB::table('assessment_rule_sets')
                        ->where('QuestionID', $question->QuestionID)
                        ->where('Version', 'v1')
                        ->where('Status', 'active')
                        ->first();

                    if (! $ruleSet) {
                        throw new RuntimeException(
                            "Question {$question->QuestionID} has no active v1 Rule Set."
                        );
                    }

                    $rubricCount = DB::table('question_rubrics')
                        ->where('QuestionID', $question->QuestionID)
                        ->count();

                    $ruleCount = DB::table('criterion_rules')
                        ->where('RuleSetID', $ruleSet->RuleSetID)
                        ->where('IsActive', true)
                        ->count();

                    if ($rubricCount < 1 || $ruleCount < 1) {
                        throw new RuntimeException(
                            "Question {$question->QuestionID} has incomplete Expert Rules."
                        );
                    }

                    $readyQuestionIds[] = (int) $question->QuestionID;
                }
            }

            if (count($readyQuestionIds) !== 60) {
                throw new RuntimeException(
                    'Expert Rules activation requires exactly 60 questions.'
                );
            }

            DB::table('question_bank')
                ->whereIn('QuestionID', $readyQuestionIds)
                ->update([
                    'IsExpertReady' => true,
                    'updated_at' => now(),
                ]);
        });

        if ($this->command) {
            $this->command->info(
                '60 Expert Rules questions were activated successfully.'
            );
        }
    }
}
