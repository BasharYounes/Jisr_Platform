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
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->string('title');
    $table->text('description')->nullable();

    $table->string('type', 32); 
    $table->string('location')->nullable();

  
    $table->decimal('salary_min', 10, 2)->nullable();
    $table->decimal('salary_max', 10, 2)->nullable();

   
    $table->decimal('match_score', 5, 2)->nullable();

  
    $table->string('status', 32)->default('open');

   
    $table->timestamp('deadline')->nullable();
    $table->timestamp('posted_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
