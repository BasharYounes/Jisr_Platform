<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'evaluation_revision_requests',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('project_evaluation_id')
                    ->constrained('project_evaluations')
                    ->cascadeOnDelete();

                $table->foreignId('requested_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('assigned_to')
                    ->constrained('users')
                    ->restrictOnDelete();

                /*
                 * lead_review:
                 * طلب مباشر من المشرف الرئيسي.
                 *
                 * student_appeal:
                 * سيُستخدم لاحقًا عندما يُقبل اعتراض الطالب.
                 */
                $table->string('source', 32)
                    ->default('lead_review');

                /*
                 * لاحقًا يمكن أن يحتوي رقم الاعتراض
                 * الذي أدى إلى إنشاء طلب التعديل.
                 */
                $table->unsignedBigInteger(
                    'source_reference_id'
                )->nullable();

                $table->text('reason');

                $table->string('status', 32)
                    ->default('pending');

                $table->text('resolution_note')
                    ->nullable();

                $table->timestamp('resolved_at')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'project_evaluation_id',
                        'status',
                    ],
                    'evaluation_revision_status_index'
                );

                $table->index(
                    [
                        'source',
                        'source_reference_id',
                    ],
                    'evaluation_revision_source_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'evaluation_revision_requests'
        );
    }
};
