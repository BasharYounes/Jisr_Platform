<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table): void {
            $table->foreignId('reported_user_id')
                ->nullable()
                ->change();

            $table->foreignId('reported_mentor_profile_id')
                ->nullable()
                ->after('reported_user_id')
                ->constrained('mentor_profiles')
                ->cascadeOnDelete();

            $table->string('context_type', 64)
                ->nullable()
                ->after('reported_mentor_profile_id');

            $table->unsignedBigInteger('context_id')
                ->nullable()
                ->after('context_type');

            $table->char('deduplication_key', 64)
                ->nullable()
                ->after('resolution_notes');

            $table->index(
                ['context_type', 'context_id'],
                'complaints_context_idx'
            );

            $table->index(
                ['complainant_user_id', 'status'],
                'complaints_complainant_status_idx'
            );

            $table->index(
                ['reported_user_id', 'status'],
                'complaints_reported_user_status_idx'
            );

            $table->index(
                ['reported_mentor_profile_id', 'status'],
                'complaints_reported_mentor_status_idx'
            );

            $table->unique(
                'deduplication_key',
                'complaints_deduplication_key_unique'
            );
        });
    }

    public function down(): void
    {
        // Mentor-target complaints cannot be represented by the old schema.
        DB::table('complaints')
            ->whereNull('reported_user_id')
            ->delete();

        Schema::table('complaints', function (Blueprint $table): void {
            $table->dropUnique('complaints_deduplication_key_unique');
            $table->dropIndex('complaints_context_idx');
            $table->dropIndex('complaints_complainant_status_idx');
            $table->dropIndex('complaints_reported_user_status_idx');
            $table->dropIndex('complaints_reported_mentor_status_idx');

            $table->dropForeign(['reported_mentor_profile_id']);

            $table->dropColumn([
                'reported_mentor_profile_id',
                'context_type',
                'context_id',
                'deduplication_key',
            ]);

            $table->foreignId('reported_user_id')
                ->nullable(false)
                ->change();
        });
    }
};
