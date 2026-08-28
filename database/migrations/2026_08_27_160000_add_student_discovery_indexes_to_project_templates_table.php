<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_templates', function (Blueprint $table): void {
            $table->index(
                ['created_by_type', 'created_at'],
                'pt_creator_type_created_at_idx'
            );

            $table->index(
                ['created_by_type', 'level', 'created_at'],
                'pt_creator_type_level_created_at_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table): void {
            $table->dropIndex('pt_creator_type_created_at_idx');
            $table->dropIndex('pt_creator_type_level_created_at_idx');
        });
    }
};
