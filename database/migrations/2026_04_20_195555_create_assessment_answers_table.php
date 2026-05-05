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
        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id('AssessmentAnswerID');
            $table->unsignedBigInteger('AssessmentQuestionAttemptID');
            $table->longText('AnswerText')->nullable();
            $table->json('AnswerJson')->nullable();
            $table->timestamp('SubmittedAt')->nullable();
            $table->timestamps();

            $table->foreign('AssessmentQuestionAttemptID')->references('AssessmentQuestionAttemptID')->on('assessment_question_attempts')->OnDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
    }
};
