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
        Schema::create('mock_interview_sessions', function (Blueprint $table) {
            $table->id();
$table->foreignId('student_id')
          ->constrained('users')
          ->cascadeOnDelete();

    $table->string('job_role', 128);

    $table->string('difficulty', 32);

    $table->json('questions_and_answers')->nullable();

    $table->decimal('overall_score', 5, 2)->nullable();

    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_interview_sessions');
    }
};
