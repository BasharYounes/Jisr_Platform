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
        Schema::create('student_skill_gap_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
          ->constrained('users')
          ->cascadeOnDelete();

    $table->foreignId('resource_id')
          ->constrained('learning_resources')
          ->cascadeOnDelete();

    $table->foreignId('gap_skill_id')
          ->constrained('skills')
          ->cascadeOnDelete();

    $table->enum('status', ['suggested', 'started', 'completed'])
      ->default('suggested');

    $table->timestamp('suggested_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_skill_gap_recommendations');
    }
};
