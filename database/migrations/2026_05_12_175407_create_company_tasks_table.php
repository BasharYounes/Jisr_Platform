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
        Schema::create('company_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
        $table->string('title', 255);
        $table->text('description');
        $table->enum('difficulty_level', ['beginner','intermediate','advanced',])->default('intermediate');
        $table->unsignedTinyInteger('duration_days');
        $table->dateTime('deadline');
        $table->unsignedInteger('max_applicants')->nullable();
        $table->unsignedInteger('max_accepted_students')->default(1);
        $table->json('deliverables')->nullable();
        $table->json('acceptance_criteria')->nullable();
        $table->enum('submission_type', [
            'github_link',
            'zip_file',
            'demo_link',
            'mixed',
        ])->default('github_link');
        $table->enum('status', [
            'draft',
            'published',
            'in_progress',
            'completed',
            'closed',
            'cancelled',
        ])->default('draft');

        $table->timestamp('published_at')->nullable();

        $table->timestamps();
        $table->softDeletes();

        $table->index(['company_id', 'status']);
        $table->index('deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_tasks');
    }
};
