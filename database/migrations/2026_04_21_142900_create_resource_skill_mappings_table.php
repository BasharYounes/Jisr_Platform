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
        Schema::create('resource_skill_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')
          ->constrained('learning_resources')
          ->cascadeOnDelete();

    $table->foreignId('skill_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->decimal('relevance_score', 5, 2)->default(1);

    $table->timestamps();

    $table->unique(['resource_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_skill_mappings');
    }
};
