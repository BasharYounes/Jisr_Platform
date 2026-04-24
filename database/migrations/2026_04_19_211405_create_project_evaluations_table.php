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
        Schema::create('project_evaluations', function (Blueprint $table) {
            $table->id();
    $table->foreignId('project_assignment_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->foreignId('supervisor_id')
          ->constrained('users')
          ->cascadeOnDelete();

    $table->decimal('total_score', 5, 2)->nullable();
    $table->decimal('final_grade', 5, 2)->nullable();

    $table->text('general_comment')->nullable();

    $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_evaluations');
    }
};
