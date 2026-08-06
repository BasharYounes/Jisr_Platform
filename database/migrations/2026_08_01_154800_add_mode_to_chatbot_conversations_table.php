<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_conversations', function (Blueprint $table): void {
            $table->string('mode', 30)
                ->default('platform_help')
                ->after('student_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_conversations', function (Blueprint $table): void {
            $table->dropIndex(['mode']);
            $table->dropColumn('mode');
        });
    }
};
