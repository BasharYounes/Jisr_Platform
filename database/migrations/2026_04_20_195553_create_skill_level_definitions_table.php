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
        Schema::create('skill_level_definitions', function (Blueprint $table) {
            $table->id('SkillLevelDefinitionID');
            $table->unsignedBigInteger('SkillID');
            $table->unsignedTinyInteger('Level');
            $table->string('Title', 100);
            $table->text('Description');
            $table->json('BehavioralIndicatorsJson')->nullable();
            $table->timestamps();

            $table->unique(['SkillID', 'Level']);
            $table->foreign('SkillID')->references('id')->on('skills')->OnDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_level_definitions');
    }
};
