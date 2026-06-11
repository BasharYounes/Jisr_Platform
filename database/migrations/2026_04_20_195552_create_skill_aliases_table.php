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
        Schema::create('skill_aliases', function (Blueprint $table) {
            $table->id('SkillAliasID');
            $table->unsignedBigInteger('SkillID');
            $table->string('Alias', 128)->unique();
            $table->string('LanguageCode', 10)->nullable();
            $table->timestamps();

            $table->foreign('SkillID')->references('id')->on('skills')->OnDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_aliases');
    }
};
