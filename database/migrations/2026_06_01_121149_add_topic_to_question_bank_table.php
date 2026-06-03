<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_bank', function (Blueprint $table) {
            if (! Schema::hasColumn('question_bank', 'Topic')) {
                $table->string('Topic', 128)->nullable()->after('QuestionType');
            }
        });
    }

    public function down(): void
    {
        Schema::table('question_bank', function (Blueprint $table) {
            if (Schema::hasColumn('question_bank', 'Topic')) {
                $table->dropColumn('Topic');
            }
        });
    }
};
