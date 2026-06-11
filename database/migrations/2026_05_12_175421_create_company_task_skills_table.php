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
        Schema::create('company_task_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_task_id')->constrained('company_tasks')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->unsignedTinyInteger('required_level')->nullable();
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->boolean('mandatory')->default(true);
            $table->timestamps();

            $table->unique(['company_task_id', 'skill_id']);

            $table->index('skill_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_task_skills');
    }
};
