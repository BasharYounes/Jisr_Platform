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
        Schema::create('assessment_skill_sessions', function (Blueprint $table) {
            $table->id('AssessmentSkillSessionID');
            $table->unsignedBigInteger('AssessmentSessionID');
            $table->unsignedBigInteger('SkillID');
            $table->decimal('InitialLevel', 3, 1)->default(0);
            $table->decimal('CurrentEstimatedLevel', 3, 1)->default(0);
            $table->decimal('FinalLevel', 3, 1)->nullable();
            $table->decimal('ConfidenceScore', 4, 2)->nullable();
            $table->unsignedInteger('QuestionCount')->default(0);
            $table->string('Status', 32)->default('pending');
            $table->timestamps();

            $table->unique(['AssessmentSessionID', 'SkillID']);
            $table->foreign('AssessmentSessionID')->references('AssessmentSessionID')->on('assessment_sessions')->OnDelete('cascade')->onUpdate('cascade');
            $table->foreign('SkillID')->references('id')->on('skills')->OnDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_skill_sessions');
    }
};
