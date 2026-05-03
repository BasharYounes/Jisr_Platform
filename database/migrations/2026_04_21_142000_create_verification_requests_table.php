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
        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_user_id')
          ->constrained('users')
          ->cascadeOnDelete();

   
    $table->string('requested_role', 32);
    

   
    $table->string('requested_specialization', 128)->nullable();

    $table->unsignedInteger('experience_years')->nullable();

    $table->text('portfolio_url')->nullable();

  
    $table->foreignId('cv_id')
          ->nullable()
          ->constrained('cvs')
          ->nullOnDelete();

 
    $table->text('motivation_message')->nullable();

  
    $table->string('status', 32)->default('pending');
    


    $table->text('review_notes')->nullable();


    $table->timestamp('reviewed_at')->nullable();
    $table->timestamp('applied_at')->useCurrent();

    $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_requests');
    }
};
