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
        Schema::create('portfolio_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // $table->foreignId('project_assignment_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('portfolioable');
            $table->enum('source', ['manual', 'project_assignment', 'company_task_assignment'])->default('manual');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('project_url', 2048)->nullable();
            $table->timestamp('completion_date')->nullable();
            $table->decimal('grade', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_projects');
    }
};
