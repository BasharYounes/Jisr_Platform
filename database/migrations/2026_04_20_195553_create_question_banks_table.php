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
        Schema::create('question_bank', function (Blueprint $table) {
            $table->id('QuestionID');
            $table->unsignedBigInteger('SkillID');
            $table->unsignedBigInteger('CareerPathID')->nullable();
            $table->unsignedTinyInteger('Level');
            $table->string('QuestionType', 32)->default('open_text');
            $table->text('QuestionText');
            $table->string('ExpectedAnswerType', 32)->default('text');
            $table->decimal('DifficultyWeight', 4, 2)->default(1.00);
            $table->boolean('IsActive')->default(true);
            $table->unsignedBigInteger('CreatedByUserId')->nullable();
            $table->timestamps();

            $table->foreign('SkillID')->references('id')->on('skills')->OnDelete('cascade')->onUpdate('cascade');
            $table->foreign('CareerPathID')->references('CareerPathID')->on('career_paths')->nullOnDelete()->OnUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_banks');
    }
};
