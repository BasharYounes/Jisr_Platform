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
        Schema::create('assessment_question_attempts', function (Blueprint $table) {
            $table->id('AssessmentQuestionAttemptID');
            $table->unsignedBigInteger('AssessmentSkillSessionID');
            $table->unsignedBigInteger('QuestionID');
            $table->unsignedTinyInteger('QuestionLevel');
            $table->timestamp('AskedAt')->nullable();
            $table->timestamp('AnsweredAt')->nullable();
            $table->string('LlmEvaluationStatus', 32)->default('pending');
            $table->decimal('RawScore', 4, 2)->nullable();
            $table->decimal('NormalizedScore', 4, 2)->nullable();
            $table->text('FeedbackText')->nullable();
            $table->json('EvaluationJson')->nullable();
            $table->timestamps();

            $table->foreign('AssessmentSkillSessionID')->references('AssessmentSkillSessionID')->on('assessment_skill_sessions')->OnDelete('cascade')->onUpdate('cascade');
            $table->foreign('QuestionID')->references('QuestionID')->on('question_bank')->OnDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_question_attempts');
    }
};
