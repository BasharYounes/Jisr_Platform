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
        Schema::create('cv_extracted_skills', function (Blueprint $table) {
            $table->id('CVExtractedSkillID');
            $table->unsignedBigInteger('CVAnalysisID');
            $table->unsignedBigInteger('SkillID')->nullable();
            $table->string('RawSkillName', 128);
            $table->text('EvidenceText')->nullable();
            $table->decimal('InitialLevel', 3, 1)->default(0);
            $table->decimal('ConfidenceScore', 4, 2)->default(0);
            $table->string('ExtractionSource', 32)->default('llm');
            $table->timestamps();

            $table->foreign('CVAnalysisID')->references('CVAnalysisID')
                ->on('c_v_analyses')->OnDelete('cascade')->onUpdate('cascade');
            $table->foreign('SkillID')->references('id')->on('skills')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_v_extracted_skills');
    }
};
