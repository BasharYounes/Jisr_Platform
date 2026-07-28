<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'project_evaluations',
            function (Blueprint $table): void {
                /*
                 * يسجل وقت بداية أول نافذة اعتراض.
                 */
                $table->timestamp('appeal_started_at')
                    ->nullable()
                    ->after('evaluated_at');

                /*
                 * يساوي appeal_started_at + 48 ساعة.
                 * لا يُعدّل عند إعادة إرسال التقييم.
                 */
                $table->timestamp('appeal_deadline_at')
                    ->nullable()
                    ->after('appeal_started_at');

                $table->index(
                    [
                        'status',
                        'appeal_deadline_at',
                    ],
                    'project_evaluations_appeal_window_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'project_evaluations',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'project_evaluations_appeal_window_index'
                );

                $table->dropColumn([
                    'appeal_started_at',
                    'appeal_deadline_at',
                ]);
            }
        );
    }
};
