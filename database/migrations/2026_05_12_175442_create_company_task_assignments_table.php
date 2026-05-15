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
        Schema::create('company_task_assignments', function (Blueprint $table) {
             $table->id();
             $table->foreignId('company_task_id')->constrained('company_tasks')->cascadeOnDelete();
             $table->foreignId('company_task_application_id')->constrained('company_task_applications')->cascadeOnDelete();
             $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
         $table->enum('status', [
            'not_started',
            'working',
            'submitted',
            'reviewed',
            'cancelled',
            'withdrawn',
        ])->default('not_started');

        $table->timestamp('started_at')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamp('completed_at')->nullable();

        $table->timestamps();
        $table->softDeletes();

        $table->unique(['company_task_id', 'student_user_id']);
        $table->unique('company_task_application_id');

        $table->index(['student_user_id', 'status']);
        $table->index(['company_task_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_task_assignments');
    }
};
