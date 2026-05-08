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
        Schema::create('c_v_analyses', function (Blueprint $table) {
            $table->id('CVAnalysisID');
            $table->unsignedBigInteger('CvId');
            $table->json('ExtractedSkillsJson')->nullable();
            $table->json('MissingCriteriaJson')->nullable();
            $table->decimal('OverallScore', 5, 2)->nullable();
            $table->string('AiModelVersion', 50)->nullable();
            $table->timestamp('AnalyzedAt')->useCurrent();
            $table->foreign('CvId')->references('CvID')->on('c_v_s')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cv_extracted_skills');
        Schema::dropIfExists('c_v_analyses');
    }
};
