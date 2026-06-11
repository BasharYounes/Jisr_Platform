<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_session_id')
                ->nullable()
                ->constrained('assessment_sessions', 'AssessmentSessionID')
                ->nullOnDelete();

            $table->foreignId('assessment_skill_session_id')
                ->nullable()
                ->constrained('assessment_skill_sessions', 'AssessmentSkillSessionID')
                ->nullOnDelete();

            $table->foreignId('assessment_question_attempt_id')
                ->nullable()
                ->constrained('assessment_question_attempts', 'AssessmentQuestionAttemptID')
                ->nullOnDelete();

            $table->foreignId('question_id')
                ->nullable()
                ->constrained('question_bank', 'QuestionID')
                ->nullOnDelete();

            $table->string('event_type', 64);

            $table->decimal('level_before', 4, 2)->nullable();
            $table->decimal('level_after', 4, 2)->nullable();

            $table->decimal('normalized_score', 5, 4)->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();

            $table->jsonb('payload')->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->index('event_type');
            $table->index('assessment_session_id');
            $table->index('assessment_skill_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_events');
    }
};
