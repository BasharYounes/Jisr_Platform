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
        Schema::create('candidate_match_snapshots', function (Blueprint $table) {
        $table->id();

        // $table->unsignedBigInteger('opportunity_id')->references('id')->on('opportunities')->onDelete('cascade')->onUpdate('cascade');
        // $table->unsignedBigInteger('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');

        // $table->decimal('skill_score',8,2)->default(0);
        // $table->decimal('project_score',8,2)->default(0);
        // $table->decimal('activity_score',8,2)->default(0);
        // $table->decimal('tag_score',8,2)->default(0);
        // $table->decimal('freshness_score',8,2)->default(0);

        // $table->decimal('final_score',8,2)->index();
        // $table->integer('rank')->nullable()->index();

        // $table->json('explanation_json')->nullable();

        // $table->timestamp('calculated_at');

        // $table->unique(['opportunity_id','user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_match_snapshots');
    }
};
