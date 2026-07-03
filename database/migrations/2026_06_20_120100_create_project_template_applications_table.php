<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_template_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_template_id')
                ->constrained('project_templates')
                ->cascadeOnDelete();
            $table->foreignId('student_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->enum('status', [
                'pending',
                'accepted',
                'rejected',
                'withdrawn',
            ])->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('supervisor_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['project_template_id', 'student_user_id'],
                'pta_template_student_unique'
            );
            $table->index(['project_template_id', 'status']);
            $table->index(['student_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_template_applications');
    }
};
