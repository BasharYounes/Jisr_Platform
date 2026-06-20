<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_evaluations', function (Blueprint $table) {
            /*
             * nullable فقط لحماية أي تقييمات قديمة موجودة قبل هذا التعديل.
             * كل تقييم جديد عبر الـAPI سيكون مرتبطاً بطالب بشكل إلزامي.
             */
            $table->foreignId('student_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unique(
                ['project_assignment_id', 'student_id'],
                'project_evaluations_assignment_student_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('project_evaluations', function (Blueprint $table) {
            $table->dropUnique('project_evaluations_assignment_student_unique');
            $table->dropForeign(['student_id']);
            $table->dropColumn('student_id');
        });
    }
};
