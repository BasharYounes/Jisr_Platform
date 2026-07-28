<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_trends', function (Blueprint $table) {
            $table->unsignedBigInteger('career_path_id')
                ->nullable()
                ->after('id');

            $table->foreign('career_path_id', 'mt_career_path_fk')
                ->references('CareerPathID')
                ->on('career_paths')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['career_path_id', 'analyzed_date'], 'mt_path_date_idx');

            $table->unique(
                ['career_path_id', 'skill_id', 'analyzed_date'],
                'mt_path_skill_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('market_trends', function (Blueprint $table) {
            $table->dropUnique('mt_path_skill_date_unique');
            $table->dropIndex('mt_path_date_idx');
            $table->dropForeign('mt_career_path_fk');
            $table->dropColumn('career_path_id');
        });
    }
};
