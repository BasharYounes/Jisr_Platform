<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
            $table->json('scoring_anchors')->nullable()->after('weight');
            $table->json('skill_impacts')->nullable()->after('scoring_anchors');
            $table->unsignedInteger('version')->default(1)->after('skill_impacts');
            $table->boolean('is_required')->default(true)->after('is_active');
        });

        Schema::table('evaluation_items', function (Blueprint $table) {
            $table->text('evidence')->nullable()->after('comment');
            $table->json('evidence_urls')->nullable()->after('evidence');
        });

        Schema::table('project_evaluations', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('final_grade');
            $table->json('summary_metrics')->nullable()->after('general_comment');
        });
    }

    public function down(): void
    {
        Schema::table('project_evaluations', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'summary_metrics',
            ]);
        });

        Schema::table('evaluation_items', function (Blueprint $table) {
            $table->dropColumn([
                'evidence',
                'evidence_urls',
            ]);
        });

        Schema::table('evaluation_criteria', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'scoring_anchors',
                'skill_impacts',
                'version',
                'is_required',
            ]);
        });
    }
};
