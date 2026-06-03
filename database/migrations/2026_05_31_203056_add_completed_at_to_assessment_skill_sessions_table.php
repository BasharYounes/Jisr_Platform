<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_skill_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_skill_sessions', 'CompletedAt')) {
                $table->timestamp('CompletedAt')->nullable()->after('Status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_skill_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('assessment_skill_sessions', 'CompletedAt')) {
                $table->dropColumn('CompletedAt');
            }
        });
    }
};
