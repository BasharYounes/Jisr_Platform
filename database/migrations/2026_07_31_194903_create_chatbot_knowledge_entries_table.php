<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('category', 80)->index();
            $table->string('question_ar');
            $table->string('question_en');
            $table->longText('answer_ar');
            $table->longText('answer_en');
            $table->json('keywords')->nullable();
            $table->json('action')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_knowledge_entries');
    }
};
