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
        Schema::create('user_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->foreignId('skill_id')
          ->constrained()
          ->cascadeOnDelete();

   
    $table->unsignedSmallInteger('proficiency_level');


    $table->decimal('confidence_score', 4, 2)->nullable();

    $table->string('source', 32)->nullable(); 
   

    $table->boolean('verified')->default(false);

    $table->timestamps();

    $table->unique(['user_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_skills');
    }
};
