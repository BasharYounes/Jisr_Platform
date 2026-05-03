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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

           $table->foreignId('opportunity_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->foreignId('user_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->foreignId('cv_id')
          ->nullable()
          ->constrained('cvs')
          ->nullOnDelete();

    $table->text('cover_letter')->nullable();

    $table->string('status', 32)->default('pending');

    $table->timestamp('applied_at')->useCurrent();
    $table->timestamp('reviewed_at')->nullable();

    $table->text('reviewer_notes')->nullable();

    $table->timestamps();

      $table->unique(['opportunity_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
