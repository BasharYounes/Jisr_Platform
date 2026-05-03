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
        Schema::create('supervisor_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_id')
          ->constrained('users')
          ->cascadeOnDelete();

    $table->foreignId('user_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->unsignedSmallInteger('rating');

    $table->text('review_text')->nullable();

    $table->timestamps();

    $table->unique(['supervisor_id', 'user_id']);
    
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervisor_reviews');
    }
};
