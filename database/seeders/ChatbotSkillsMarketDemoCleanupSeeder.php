<?php

namespace Database\Seeders;

use App\Models\AssessmentSession;
use App\Models\UserSkill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChatbotSkillsMarketDemoCleanupSeeder extends Seeder
{
    private const SOURCE = 'chatbot_demo';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'ChatbotSkillsMarketDemoCleanupSeeder is allowed only in local or testing environments.'
            );
        }

        $studentId = (int) env('CHATBOT_DEMO_STUDENT_ID', 2);

        DB::transaction(function () use ($studentId): void {
            $demoSessions = AssessmentSession::query()
                ->where('UserID', $studentId)
                ->get()
                ->filter(function (AssessmentSession $session): bool {
                    return ($session->InitialSkillsSnapshotJson['source'] ?? null) === self::SOURCE;
                });

            foreach ($demoSessions as $session) {
                // assessment_skill_sessions are removed through the session cascade.
                $session->delete();
            }

            UserSkill::query()
                ->where('UserId', $studentId)
                ->where('Source', self::SOURCE)
                ->delete();
        });

        $this->command?->info(
            "Chatbot demo skills and assessment data removed for student {$studentId}."
        );
    }
}
