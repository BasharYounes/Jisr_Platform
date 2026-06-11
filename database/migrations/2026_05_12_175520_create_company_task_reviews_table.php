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
        Schema::create('company_task_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_task_submission_id')
                ->constrained('company_task_submissions')
                ->cascadeOnDelete();

            $table->foreignId('company_task_assignment_id')
                ->constrained('company_task_assignments')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('student_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('quality_score');
            $table->unsignedTinyInteger('commitment_score');
            $table->unsignedTinyInteger('communication_score');

            $table->decimal('total_score', 5, 2)->nullable();

            $table->enum('final_decision', [
                'approved',
                'needs_changes',
                'rejected',
            ])->default('approved');

            $table->text('feedback')->nullable();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('company_task_submission_id');

            $table->index(['company_task_assignment_id']);
            $table->index(['student_user_id']);
            $table->index(['company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_task_reviews');
    }
};
