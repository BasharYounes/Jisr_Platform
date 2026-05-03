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
        Schema::create('market_trends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')
          ->constrained()
          ->cascadeOnDelete();

   
    $table->decimal('demand_score', 5, 2);

  
    $table->string('trend_direction', 16);
   

   
    $table->integer('source_job_count')->default(0);

 
    $table->date('analyzed_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_trends');
    }
};
