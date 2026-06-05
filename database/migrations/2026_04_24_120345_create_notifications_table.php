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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // $table->string('type');
            // $table->text('body')->nullable();
            // $table->json('data')->nullable();
            // $table->boolean('is_read')->default(false);
            // $table->enum('priority', ['low', 'medium', 'high'])->default('low');
            // $table->enum('channel', ['in_app', 'push', 'both'])->default('in_app');
            // $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // $table->timestamps();
            // $table->timestamp('read_at')->nullable();
            // $table->index(['user_id', 'read_at']);
            // $table->index('type');
            // $table->index('priority');
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('type', 80);

            $table->string('title', 120);
            $table->text('body')->nullable();

            $table->string('notifiable_type')->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();

            $table->json('data')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
