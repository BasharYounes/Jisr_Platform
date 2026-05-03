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
        Schema::create('career_path_skills', function (Blueprint $table) {
            $table->id('CareerPathSkillID');
            $table->unsignedBigInteger('CareerPathID');
            $table->unsignedBigInteger('SkillID');
            $table->decimal('RequiredLevel', 3, 1);
            $table->decimal('Weight', 4, 2)->default(1.00);
            $table->boolean('IsCore')->default(true);
            $table->timestamps();

            $table->unique(['CareerPathID', 'SkillID']);
            $table->foreign('CareerPathID')->references('CareerPathID')->on('career_paths')->OnDelete('cascade')->onUpdate('cascade');
            $table->foreign('SkillID')->references('id')->on('skills')->OnDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('career_path_skills');
    }
};
