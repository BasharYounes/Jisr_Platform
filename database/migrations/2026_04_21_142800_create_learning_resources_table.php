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
        Schema::create('learning_resources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('SkillID');

            $table->string('Title');
            $table->string('Url');

            $table->string('Type'); // video, article, course, project
            $table->unsignedTinyInteger('Level'); // 1-5

            $table->decimal('EstimatedHours', 5, 2)->nullable();

            $table->string('Provider')->nullable(); // YouTube, Coursera
            $table->string('Language', 10)->default('en');

            $table->boolean('IsFree')->default(true);
            $table->boolean('IsActive')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_resources');
    }
};
