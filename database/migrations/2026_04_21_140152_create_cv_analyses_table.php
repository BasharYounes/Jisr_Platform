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
        Schema::create('cv_analyses', function (Blueprint $table) {
            $table->id();
 $table->foreignId('cv_id')
          ->constrained('cvs')
          ->cascadeOnDelete();

   
    $table->json('extracted_skills')->nullable();

    $table->json('missing_criteria')->nullable();


    $table->decimal('overall_score', 5, 2)->nullable();

    $table->string('ai_model_version', 32)->nullable();

    $table->timestamp('analyzed_at')->useCurrent();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cv_analyses');
    }
};
