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
        Schema::create('evaluation_items', function (Blueprint $table) {
            $table->id();
    $table->foreignId('project_evaluation_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->foreignId('evaluation_criteria_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->decimal('score', 5, 2);

    $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_items');
    }
};
