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
        Schema::create('project_assignment_tasks', function (Blueprint $table) {
        $table->id();

        $table->foreignId('project_assignment_id')
            ->constrained('project_assignments')
            ->cascadeOnDelete();

        $table->foreignId('project_task_id')
            ->nullable()
            ->constrained('project_tasks')
            ->nullOnDelete();

        $table->string('title');
        $table->text('description')->nullable();

        $table->string('status', 32)->default('todo');

        $table->unsignedInteger('estimated_hours')->nullable();
        $table->text('submission_url')->nullable();
        $table->text('github_branch_or_link')->nullable();

        $table->text('supervisor_feedback')->nullable();

        $table->timestamp('started_at')->nullable();
        $table->timestamp('submitted_at')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->timestamp('completed_at')->nullable();

        $table->unsignedInteger('order_index')->default(0);

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_assignment_tasks');
    }
};
