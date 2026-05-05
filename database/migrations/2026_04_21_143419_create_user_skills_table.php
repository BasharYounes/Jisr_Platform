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
            $table->unsignedBigInteger('UserId');
            $table->unsignedBigInteger('SkillId');
            $table->integer('ProficiencyLevel')->nullable();
            $table->decimal('ConfidenceScore', 5, 2)->nullable();
            $table->string('Source')->nullable();
            $table->boolean('Verified')->default(false);
            $table->foreign('UserId')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('SkillId')->references('id')->on('skills')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamps();
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
