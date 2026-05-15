<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('evaluation_criterias') && !Schema::hasTable('evaluation_criteria')) {
            Schema::rename('evaluation_criterias', 'evaluation_criteria');
        }

        if (!Schema::hasTable('evaluation_criteria')) {
            Schema::create('evaluation_criteria', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('category')->nullable();
                $table->unsignedSmallInteger('max_score');
                $table->decimal('weight', 5, 2)->default(1);
                $table->json('scoring_anchors')->nullable();
                $table->json('skill_impacts')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_required')->default(true);
                $table->timestamps();
            });

            return;
        }

        Schema::table('evaluation_criteria', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_criteria', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (!Schema::hasColumn('evaluation_criteria', 'category')) {
                $table->string('category')->nullable()->after('name');
            }

            if (!Schema::hasColumn('evaluation_criteria', 'max_score')) {
                $table->unsignedSmallInteger('max_score')->after('category');
            }

            if (!Schema::hasColumn('evaluation_criteria', 'weight')) {
                $table->decimal('weight', 5, 2)->default(1)->after('max_score');
            }

            if (!Schema::hasColumn('evaluation_criteria', 'scoring_anchors')) {
                $table->json('scoring_anchors')->nullable()->after('weight');
            }

            if (!Schema::hasColumn('evaluation_criteria', 'skill_impacts')) {
                $table->json('skill_impacts')->nullable()->after('scoring_anchors');
            }

            if (!Schema::hasColumn('evaluation_criteria', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('skill_impacts');
            }

            if (!Schema::hasColumn('evaluation_criteria', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('version');
            }

            if (!Schema::hasColumn('evaluation_criteria', 'is_required')) {
                $table->boolean('is_required')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('evaluation_criteria') && !Schema::hasTable('evaluation_criterias')) {
            Schema::rename('evaluation_criteria', 'evaluation_criterias');
        }
    }
};
