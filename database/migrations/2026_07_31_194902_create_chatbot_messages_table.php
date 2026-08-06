<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('chatbot_conversations')
                ->cascadeOnDelete();
            $table->uuid('client_message_id')->nullable();
            $table->enum('role', ['user', 'assistant']);
            $table->longText('content');
            $table->enum('language', ['ar', 'en']);
            $table->enum('status', ['pending', 'completed', 'failed'])
                ->default('completed');
            $table->json('actions')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->timestamps();

            $table->unique(
                ['conversation_id', 'client_message_id'],
                'chatbot_messages_conversation_client_unique'
            );
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
    }
};
