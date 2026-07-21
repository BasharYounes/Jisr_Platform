<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'project_evaluation_appeals',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'project_evaluation_id'
                )
                    ->constrained('project_evaluations')
                    ->cascadeOnDelete();

                $table->foreignId('student_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                /*
                 * يستطيع الطالب تقديم أكثر من اعتراض،
                 * لذلك لا يوجد Unique Index على التقييم.
                 */
                $table->text('reason');

                $table->string('status', 32)
                    ->default('pending');

                /*
                 * نسخة كاملة من التقييم وقت تقديم الاعتراض.
                 * تبقى محفوظة حتى لو تغير التقييم لاحقًا.
                 */
                $table->json('evaluation_snapshot');

                /*
                 * المشرف الرئيسي الذي راجع الاعتراض.
                 */
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->text('review_notes')
                    ->nullable();

                $table->timestamp('reviewed_at')
                    ->nullable();

                /*
                 * عند قبول الاعتراض نربطه بطلب التعديل
                 * الذي تم إنشاؤه أو استخدامه.
                 */
                $table->foreignId('revision_request_id')
                    ->nullable()
                    ->constrained(
                        'evaluation_revision_requests'
                    )
                    ->nullOnDelete();

                $table->timestamps();

                $table->index(
                    [
                        'project_evaluation_id',
                        'student_id',
                        'status',
                    ],
                    'evaluation_appeals_lookup_index'
                );

                $table->index(
                    [
                        'status',
                        'created_at',
                    ],
                    'evaluation_appeals_status_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'project_evaluation_appeals'
        );
    }
};
