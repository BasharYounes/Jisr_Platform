<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_template_applications', function (Blueprint $table) {
            $table->foreignId('project_assignment_id')
                ->nullable()
                ->after('student_user_id')
                ->constrained('project_assignments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_template_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_assignment_id');
        });
    }
};
