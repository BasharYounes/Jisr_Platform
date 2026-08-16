<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('chatbot_conversations')
            ->where('mode', 'student_data')
            ->update(['mode' => 'skills_market_analysis']);

        DB::table('chatbot_conversations')
            ->where('mode', 'career_guidance')
            ->update(['mode' => 'opportunity_matching']);
    }

    public function down(): void
    {
        DB::table('chatbot_conversations')
            ->where('mode', 'skills_market_analysis')
            ->update(['mode' => 'student_data']);

        DB::table('chatbot_conversations')
            ->where('mode', 'opportunity_matching')
            ->update(['mode' => 'career_guidance']);
    }
};
