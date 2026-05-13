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
        Schema::create('company_task_progress_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_task_assignment_id')
            ->constrained('company_task_assignments')
            ->cascadeOnDelete();

        $table->foreignId('student_user_id')
            ->constrained('users')
            ->cascadeOnDelete();

        $table->string('title', 255)->nullable();

        $table->text('description');

        $table->unsignedTinyInteger('progress_percentage')->nullable();

        $table->string('github_url')->nullable();
        $table->string('demo_url')->nullable();

        $table->json('attachments')->nullable();

        $table->timestamps();
        $table->softDeletes();

       $table->index(
    ['company_task_assignment_id', 'created_at'],
    'ctp_updates_assignment_created_idx'
);

$table->index(
    ['student_user_id', 'created_at'],
    'ctp_updates_student_created_idx'
);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_task_progress_updates');
    }
};
