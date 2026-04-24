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
        Schema::create('opportunity_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->foreignId('skill_id')
          ->constrained()
          ->cascadeOnDelete();

    
    $table->unsignedSmallInteger('required_level')->default(1);

 
    $table->boolean('mandatory')->default(true);

    $table->decimal('weight', 3, 2)->default(1);


    $table->unique(['opportunity_id', 'skill_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunity_skills');
    }
};
