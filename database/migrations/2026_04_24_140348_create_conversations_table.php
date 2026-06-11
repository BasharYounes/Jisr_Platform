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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->morphs('conversationable');
            // conversationable_type
            // conversationable_id
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);

            //         $table->foreignId('conversation_id')
            //       ->constrained()
            //       ->cascadeOnDelete();

            // $table->foreignId('user_id')
            //       ->constrained()
            //       ->cascadeOnDelete();

            // $table->string('role')->nullable();

            // $table->timestamps();

            // $table->unique(['conversation_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
