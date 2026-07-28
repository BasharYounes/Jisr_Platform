<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores external or dataset-based job postings before and after market analysis.
     */
    public function up(): void
    {
        Schema::create('market_job_postings', function (Blueprint $table) {
            $table->id();

            // Source metadata: dataset, api, manual later if needed.
            $table->string('source_type', 32)->default('dataset');
            $table->string('source_name', 128)->nullable();
            $table->string('external_id', 191)->nullable();
            $table->string('url', 2048)->nullable();

            // Main job content.
            $table->string('title', 255);
            $table->longText('description');
            $table->string('company_name', 255)->nullable();
            $table->string('location', 255)->nullable();

            // Classification and analysis support.
            $table->string('language', 10)->nullable(); // ar, en, mixed, unknown
            $table->unsignedBigInteger('career_path_id')->nullable();

            // Dates.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('imported_at')->nullable();

            // Processing state.
            $table->string('status', 32)->default('active');
            // active, archived, duplicate, excluded

            // Used to prevent duplicate imports when no external_id exists.
            $table->string('content_hash', 64)->unique();

            $table->timestamps();

            $table->foreign('career_path_id')
                ->references('CareerPathID')
                ->on('career_paths')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['source_type', 'source_name']);
            $table->index(['career_path_id', 'status']);
            $table->index(['language']);
            $table->index(['published_at']);
            $table->unique(['source_name', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_job_postings');
    }
};
