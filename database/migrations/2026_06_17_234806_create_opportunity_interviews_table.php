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
        Schema::create('opportunity_interviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_id')
                ->constrained('applications')
                ->cascadeOnDelete();

            $table->foreignId('opportunity_id')
                ->constrained('opportunities')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('student_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('scheduled_at');

            $table->enum('meeting_type', [
                'online',
                'onsite',
                'phone',
            ])->default('online');

            $table->string('meeting_link')->nullable();
            $table->string('location')->nullable();

            $table->enum('status', [
                'scheduled',
                'rescheduled',
                'completed',
                'cancelled',
            ])->default('scheduled');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique('application_id');

            $table->index(['company_id', 'status']);
            $table->index(['student_user_id', 'status']);
            $table->index('scheduled_at');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunity_interviews');
    }
};
