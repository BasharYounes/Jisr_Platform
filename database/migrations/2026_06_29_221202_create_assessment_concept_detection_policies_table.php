<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'assessment_concept_detection_policies',
            function (Blueprint $table) {
                $table->id('DetectionPolicyID');

                $table->unsignedBigInteger('ConceptID');

                /*
                 * Supported values for this phase:
                 * alias, semantic, code_pattern
                 */
                $table->string('DetectorType', 32);

                /*
                 * ar, en, or any.
                 * "any" means the policy is language-independent.
                 */
                $table->string('Language', 8)
                    ->default('any');

                /*
                 * Examples:
                 *
                 * semantic:
                 * {"anchor_terms":["المتغير","معرف","اسم"]}
                 *
                 * code_pattern:
                 * {"pattern_key":"python_assignment_literal"}
                 */
                $table->json('ConfigurationJson')
                    ->nullable();

                $table->boolean('IsActive')
                    ->default(true);

                $table->timestamps();

                $table->foreign('ConceptID', 'acdp_concept_fk')
                    ->references('ConceptID')
                    ->on('assessment_concepts')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');

                $table->unique(
                    ['ConceptID', 'DetectorType', 'Language'],
                    'concept_policy_unique'
                );

                $table->index(
                    ['ConceptID', 'IsActive'],
                    'concept_policy_lookup_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'assessment_concept_detection_policies'
        );
    }
};
