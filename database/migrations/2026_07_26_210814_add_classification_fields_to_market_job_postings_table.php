<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_job_postings', function (Blueprint $table) {
            $table->string('classification_status', 32)
                ->default('pending')
                ->index();

            $table->string('classification_method', 50)
                ->nullable();

            /*
             * درجة قواعد وليست نسبة احتمال.
             */
            $table->decimal('classification_score', 8, 3)
                ->nullable();

            /*
             * إشارات العنوان والمهارات ودرجات المسارات.
             */
            $table->json('classification_evidence')
                ->nullable();

            $table->timestamp('classified_at')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('market_job_postings', function (Blueprint $table) {
            $table->dropIndex([
                'classification_status',
            ]);

            $table->dropColumn([
                'classification_status',
                'classification_method',
                'classification_score',
                'classification_evidence',
                'classified_at',
            ]);
        });
    }
};
