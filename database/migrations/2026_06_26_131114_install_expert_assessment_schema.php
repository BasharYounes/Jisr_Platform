<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Extend existing assessment tables
        |--------------------------------------------------------------------------
        */

        Schema::table('question_bank', function (Blueprint $table) {
            $table->string('EvaluationEngine', 32)
                ->default('legacy_llm');

            $table->string('RuleSetVersion', 32)
                ->nullable();

            $table->boolean('IsExpertReady')
                ->default(false);

            $table->index(
                [
                    'SkillID',
                    'IsActive',
                    'IsExpertReady',
                    'EvaluationEngine',
                    'Level',
                ],
                'qbank_expert_selection_idx'
            );
        });

        Schema::table('question_rubrics', function (Blueprint $table) {
            $table->string('CriterionCode', 100)
                ->nullable();

            $table->text('FeedbackOnPass')
                ->nullable();

            $table->text('FeedbackOnFail')
                ->nullable();

            $table->index(
                ['QuestionID', 'CriterionCode'],
                'qrubric_question_code_idx'
            );
        });

        Schema::table('assessment_question_attempts', function (Blueprint $table) {
            $table->string('EvaluationEngine', 32)
                ->default('legacy_llm');

            $table->string('EvaluationStatus', 32)
                ->default('pending');

            $table->string('EvaluationEngineVersion', 32)
                ->nullable();

            $table->index(
                ['EvaluationEngine', 'EvaluationStatus'],
                'attempt_evaluation_status_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | 2. Assessment concepts and NLP knowledge base
        |--------------------------------------------------------------------------
        */

        Schema::create('assessment_concepts', function (Blueprint $table) {
            $table->id('ConceptID');

            $table->string('ConceptCode', 150)
                ->unique();

            $table->string('NameAr', 255);

            $table->string('NameEn', 255);

            $table->text('Description')
                ->nullable();

            $table->boolean('IsActive')
                ->default(true);

            $table->timestamps();
        });

        Schema::create('assessment_concept_aliases', function (Blueprint $table) {
            $table->id('ConceptAliasID');

            $table->unsignedBigInteger('ConceptID');

            $table->string('Language', 8);

            $table->string('AliasText', 500);

            $table->string('NormalizedAlias', 500);

            $table->string('MatchType', 32)
                ->default('phrase');

            $table->decimal('MinimumSimilarity', 5, 4)
                ->nullable();

            $table->boolean('IsActive')
                ->default(true);

            $table->timestamps();

            $table->foreign('ConceptID', 'aca_concept_fk')
                ->references('ConceptID')
                ->on('assessment_concepts')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(
                ['ConceptID', 'Language', 'IsActive'],
                'concept_alias_lookup_idx'
            );
        });

        Schema::create('assessment_concept_examples', function (Blueprint $table) {
            $table->id('ConceptExampleID');

            $table->unsignedBigInteger('ConceptID');

            $table->string('Language', 8);

            $table->text('ExampleText');

            $table->decimal('MinimumSimilarity', 5, 4)
                ->default(0.7800);

            $table->boolean('IsPositive')
                ->default(true);

            $table->boolean('IsActive')
                ->default(true);

            $table->timestamps();

            $table->foreign('ConceptID', 'ace_concept_fk')
                ->references('ConceptID')
                ->on('assessment_concepts')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->index(
                ['ConceptID', 'Language', 'IsActive'],
                'concept_example_lookup_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | 3. Expert System rule definitions
        |--------------------------------------------------------------------------
        */

        Schema::create('assessment_rule_sets', function (Blueprint $table) {
            $table->id('RuleSetID');

            $table->unsignedBigInteger('QuestionID');

            $table->string('RuleSetCode', 150)
                ->unique();

            $table->string('Version', 32);

            $table->string('Status', 32)
                ->default('draft');

            $table->unsignedBigInteger('CreatedByUserId')
                ->nullable();

            $table->timestamp('ActivatedAt')
                ->nullable();

            $table->timestamps();

            $table->foreign('QuestionID', 'ars_question_fk')
                ->references('QuestionID')
                ->on('question_bank')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('CreatedByUserId', 'ars_creator_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete()
                ->onUpdate('cascade');

            $table->unique(
                ['QuestionID', 'Version'],
                'ruleset_question_version_unique'
            );

            $table->index(
                ['QuestionID', 'Status'],
                'ruleset_question_status_idx'
            );
        });

        Schema::create('criterion_rules', function (Blueprint $table) {
            $table->id('CriterionRuleID');

            $table->unsignedBigInteger('RuleSetID');

            $table->unsignedBigInteger('QuestionRubricID');

            $table->string('RuleCode', 150);

            $table->string('RuleType', 32);

            $table->unsignedSmallInteger('Priority')
                ->default(100);

            $table->json('ConditionsJson');

            $table->decimal('AwardScore', 6, 2)
                ->default(0);

            $table->text('FeedbackTemplate')
                ->nullable();

            $table->boolean('IsActive')
                ->default(true);

            $table->timestamps();

            $table->foreign('RuleSetID', 'cr_ruleset_fk')
                ->references('RuleSetID')
                ->on('assessment_rule_sets')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('QuestionRubricID', 'cr_rubric_fk')
                ->references('QuestionRubricID')
                ->on('question_rubrics')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->unique(
                ['RuleSetID', 'RuleCode'],
                'criterion_rule_set_code_unique'
            );

            $table->index(
                ['RuleSetID', 'QuestionRubricID', 'Priority'],
                'criterion_rule_execution_idx'
            );
        });

        Schema::create('assessment_contradiction_rules', function (Blueprint $table) {
            $table->id('ContradictionRuleID');

            $table->unsignedBigInteger('RuleSetID');

            $table->unsignedBigInteger('TriggerConceptID');

            $table->string('Code', 150);

            $table->string('Severity', 32)
                ->default('block_criterion');

            $table->text('FeedbackAr');

            $table->boolean('IsActive')
                ->default(true);

            $table->timestamps();

            $table->foreign('RuleSetID', 'acr_ruleset_fk')
                ->references('RuleSetID')
                ->on('assessment_rule_sets')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('TriggerConceptID', 'acr_trigger_fk')
                ->references('ConceptID')
                ->on('assessment_concepts')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->unique(
                ['RuleSetID', 'Code'],
                'contradiction_rule_set_code_unique'
            );
        });

        Schema::create('assessment_contradiction_rule_rubrics', function (Blueprint $table) {
            $table->id('ContradictionRuleRubricID');

            $table->unsignedBigInteger('ContradictionRuleID');

            $table->unsignedBigInteger('QuestionRubricID');

            $table->timestamps();

            $table->foreign('ContradictionRuleID', 'acrr_rule_fk')
                ->references('ContradictionRuleID')
                ->on('assessment_contradiction_rules')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('QuestionRubricID', 'acrr_rubric_fk')
                ->references('QuestionRubricID')
                ->on('question_rubrics')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->unique(
                ['ContradictionRuleID', 'QuestionRubricID'],
                'contradiction_rule_rubric_unique'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | 4. Evaluation audit trail and extracted evidence
        |--------------------------------------------------------------------------
        */

        Schema::create('assessment_evaluation_runs', function (Blueprint $table) {
            $table->id('EvaluationRunID');

            $table->unsignedBigInteger('AssessmentQuestionAttemptID');

            $table->unsignedBigInteger('RuleSetID')
                ->nullable();

            $table->string('Engine', 32)
                ->default('expert_rules');

            $table->string('EngineVersion', 32)
                ->default('v1');

            $table->string('Status', 32)
                ->default('pending');

            $table->string('DetectedLanguage', 8)
                ->nullable();

            $table->decimal('TotalScore', 6, 2)
                ->nullable();

            $table->decimal('NormalizedScore', 5, 4)
                ->nullable();

            $table->text('FeedbackAr')
                ->nullable();

            $table->json('EvaluationJson')
                ->nullable();

            $table->timestamp('RequestedAt')
                ->nullable();

            $table->timestamp('CompletedAt')
                ->nullable();

            $table->timestamps();

            $table->foreign('AssessmentQuestionAttemptID', 'aer_attempt_fk')
                ->references('AssessmentQuestionAttemptID')
                ->on('assessment_question_attempts')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('RuleSetID', 'aer_ruleset_fk')
                ->references('RuleSetID')
                ->on('assessment_rule_sets')
                ->nullOnDelete()
                ->onUpdate('cascade');

            $table->index(
                ['AssessmentQuestionAttemptID', 'Status'],
                'eval_run_attempt_status_idx'
            );
        });

        Schema::create('assessment_evaluation_evidence', function (Blueprint $table) {
            $table->id('EvidenceID');

            $table->unsignedBigInteger('EvaluationRunID');

            $table->unsignedBigInteger('ConceptID');

            $table->unsignedBigInteger('QuestionRubricID')
                ->nullable();

            $table->longText('EvidenceText');

            $table->unsignedSmallInteger('SentenceIndex')
                ->nullable();

            $table->string('Language', 8)
                ->nullable();

            $table->string('DetectionMethod', 32);

            $table->decimal('SimilarityScore', 5, 4)
                ->nullable();

            $table->boolean('IsNegated')
                ->default(false);

            $table->boolean('IsContradiction')
                ->default(false);

            $table->json('MetadataJson')
                ->nullable();

            $table->timestamps();

            $table->foreign('EvaluationRunID', 'aee_run_fk')
                ->references('EvaluationRunID')
                ->on('assessment_evaluation_runs')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('ConceptID', 'aee_concept_fk')
                ->references('ConceptID')
                ->on('assessment_concepts')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('QuestionRubricID', 'aee_rubric_fk')
                ->references('QuestionRubricID')
                ->on('question_rubrics')
                ->nullOnDelete()
                ->onUpdate('cascade');

            $table->index(
                ['EvaluationRunID', 'ConceptID'],
                'evaluation_evidence_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_evaluation_evidence');
        Schema::dropIfExists('assessment_evaluation_runs');
        Schema::dropIfExists('assessment_contradiction_rule_rubrics');
        Schema::dropIfExists('assessment_contradiction_rules');
        Schema::dropIfExists('criterion_rules');
        Schema::dropIfExists('assessment_rule_sets');
        Schema::dropIfExists('assessment_concept_examples');
        Schema::dropIfExists('assessment_concept_aliases');
        Schema::dropIfExists('assessment_concepts');

        Schema::table('assessment_question_attempts', function (Blueprint $table) {
            $table->dropIndex('attempt_evaluation_status_idx');

            $table->dropColumn([
                'EvaluationEngine',
                'EvaluationStatus',
                'EvaluationEngineVersion',
            ]);
        });

        Schema::table('question_rubrics', function (Blueprint $table) {
            $table->dropIndex('qrubric_question_code_idx');

            $table->dropColumn([
                'CriterionCode',
                'FeedbackOnPass',
                'FeedbackOnFail',
            ]);
        });

        Schema::table('question_bank', function (Blueprint $table) {
            $table->dropIndex('qbank_expert_selection_idx');

            $table->dropColumn([
                'EvaluationEngine',
                'RuleSetVersion',
                'IsExpertReady',
            ]);
        });
    }
};
