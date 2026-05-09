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
            $table->id('UserSkillID');
            $table->foreignId('UserId')->constrained('users')->cascadeOnDelete();
            $table->foreignId('SkillId')->constrained('skills')->cascadeOnDelete();
            $table->unsignedTinyInteger('ProficiencyLevel')->default(1);
            $table->decimal('ConfidenceScore', 4, 2)->default(0);
            $table->string('Source', 50)->default('manual');
            $table->boolean('Verified')->default(false);
            $table->timestamps();

            $table->unique(['UserId', 'SkillId']);
            $table->index(['UserId', 'ProficiencyLevel']);
            $table->index('SkillId');
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
