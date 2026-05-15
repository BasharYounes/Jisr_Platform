<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_assignment_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_assignment_id')
                ->constrained('project_assignments')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role')->nullable();
            $table->string('status')->default('active');

            $table->timestamps();

            $table->unique(['project_assignment_id', 'student_id'], 'pam_unique_idx');
        });

        Schema::table('project_assignment_tasks', function (Blueprint $table) {
            $table->foreignId('assigned_student_id')
                ->nullable()
                ->after('project_assignment_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_assignment_tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_student_id']);
            $table->dropColumn('assigned_student_id');
        });

        Schema::dropIfExists('project_assignment_members');
    }
};
