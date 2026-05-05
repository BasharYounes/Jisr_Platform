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
        Schema::create('assessment_sessions', function (Blueprint $table) {
            $table->id('AssessmentSessionID');
            $table->unsignedBigInteger('UserID');
            $table->unsignedBigInteger('CvID')->nullable();
            $table->unsignedBigInteger('CareerPathID');
            $table->string('Status', 32)->default('pending');
            $table->json('InitialSkillsSnapshotJson')->nullable();
            $table->json('FinalResultsJson')->nullable();
            $table->timestamp('StartedAt')->nullable();
            $table->timestamp('CompletedAt')->nullable();
            $table->timestamps();

            $table->foreign('UserID')->references('id')->on('users')->OnDelete('cascade')->onUpdate('cascade');
            // $table->foreign('CvID')->references('CvID')->on('cvs')->nullOnDelete();
            $table->foreign('CareerPathID')->references('CareerPathID')->on('career_paths')->OnDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_sessions');
    }
};
