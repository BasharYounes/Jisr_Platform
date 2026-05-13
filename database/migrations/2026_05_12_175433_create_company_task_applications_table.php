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
        Schema::create('company_task_applications', function (Blueprint $table) {
            $table->id();
             $table->foreignId('company_task_id')->constrained('company_tasks')->cascadeOnDelete();

            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
         $table->text('message')->nullable();

          $table->string('portfolio_url')->nullable();
         $table->string('github_url')->nullable();

            $table->enum('status', [
            'pending',
            'accepted',
            'rejected',
            'withdrawn',
            ])->default('pending');

            $table->decimal('match_score', 5, 2)->nullable();

            $table->json('match_reasons')->nullable();  

            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

          $table->text('company_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

         $table->unique(['company_task_id', 'student_user_id']);
         $table->index(['company_task_id', 'status']);
         $table->index(['student_user_id', 'status']);
         $table->index('match_score');
      });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_task_applications');
    }
};
