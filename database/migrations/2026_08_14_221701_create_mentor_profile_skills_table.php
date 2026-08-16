<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_profile_skills', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mentor_profile_id')
                ->constrained('mentor_profiles')
                ->cascadeOnDelete();

            $table->foreignId('skill_id')
                ->constrained('skills')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['mentor_profile_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_profile_skills');
    }
};
