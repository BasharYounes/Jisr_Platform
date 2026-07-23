<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the evidence that a specific skill appeared in a specific market job posting.
     */
    public function up(): void
    {
        Schema::create('market_job_posting_skill_occurrences', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('market_job_posting_id');
            $table->unsignedBigInteger('skill_id');
            $table->unsignedBigInteger('skill_alias_id')->nullable();

            $table->string('matched_text', 255);
            $table->string('language', 10)->nullable();

            // For alias matching, confidence is usually 1.00.
            $table->decimal('confidence', 4, 2)->default(1.00);

            // Current value: alias_match. Future values: ner_model, llm_review, hybrid.
            $table->string('extraction_method', 64)->default('alias_match');

            // Optional evidence around the matched word/phrase.
            $table->text('context')->nullable();

            $table->timestamps();

            $table->foreign('market_job_posting_id', 'mjpso_job_fk')
                ->references('id')
                ->on('market_job_postings')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('skill_id', 'mjpso_skill_fk')
                ->references('id')
                ->on('skills')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('skill_alias_id', 'mjpso_alias_fk')
                ->references('SkillAliasID')
                ->on('skill_aliases')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // We count each skill once per job posting.
            $table->unique(
                ['market_job_posting_id', 'skill_id'],
                'market_job_skill_unique'
            );

            $table->index('skill_id', 'mjpso_skill_idx');
            $table->index('skill_alias_id', 'mjpso_alias_idx');
            $table->index('extraction_method', 'mjpso_method_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_job_posting_skill_occurrences');
    }
};
