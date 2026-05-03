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
        Schema::create('question_rubrics', function (Blueprint $table) {
            $table->id('QuestionRubricID');
            $table->unsignedBigInteger('QuestionID');
            $table->string('CriterionName', 255);
            $table->text('CriterionDescription');
            $table->decimal('MaxScore', 4, 2);
            $table->decimal('Weight', 4, 2)->default(1.00);
            $table->json('KeywordsJson')->nullable();
            $table->text('SampleGoodAnswer')->nullable();
            $table->text('SampleBadAnswer')->nullable();
            $table->integer('OrderIndex')->default(0);
            $table->timestamps();

            $table->foreign('QuestionID')->references('QuestionID')->on('question_bank')->OnDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_rubrics');
    }
};
