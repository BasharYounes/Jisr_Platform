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
        Schema::create('a_i_learning_plans', function (Blueprint $table) {
            $table->id('AILearningPlanID');
            $table->unsignedBigInteger('AssessmentSessionID');
            $table->unsignedBigInteger('UserID');

            $table->string('Status', 32)->default('generated');
            $table->unsignedTinyInteger('Weeks')->default(4);
            $table->unsignedTinyInteger('HoursPerWeek')->default(5);

            $table->json('InputSnapshotJson')->nullable();
            $table->json('PlanJson');
            $table->text('SummaryText')->nullable();

            $table->string('AiModelVersion', 64)->nullable();
            $table->timestamp('GeneratedAt')->nullable();
            $table->timestamps();

            $table->foreign('AssessmentSessionID')
                ->references('AssessmentSessionID')
                ->on('assessment_sessions')
                ->cascadeOnDelete();

            $table->foreign('UserID')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_i_learning_plans');
    }
};
