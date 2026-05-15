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
        Schema::create('company_task_submissions', function (Blueprint $table) {
            $table->id();
             $table->foreignId('company_task_assignment_id')
            ->constrained('company_task_assignments')
            ->cascadeOnDelete();

        $table->foreignId('student_user_id')
            ->constrained('users')
            ->cascadeOnDelete();

        $table->string('github_url')->nullable();

        $table->string('demo_url')->nullable();

        $table->string('zip_file_path')->nullable();

        $table->text('notes')->nullable();

        $table->enum('status', [
            'submitted',
            'needs_changes',
            'approved',
            'rejected',
        ])->default('submitted');

        $table->timestamp('submitted_at')->nullable();

        $table->timestamps();
        $table->softDeletes();

        $table->index(['company_task_assignment_id', 'status']);
        $table->index(['student_user_id', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_task_submissions');
    }
};
