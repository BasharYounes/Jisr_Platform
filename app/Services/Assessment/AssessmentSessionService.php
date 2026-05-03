<?php

namespace App\Services\Assessment;

use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\UserSkill;
use Illuminate\Support\Facades\DB;

class AssessmentSessionService
{
    public function create(
        int $userId,
        int $careerPathId,
        ?int $cvId,
        array $skillIds
    ): AssessmentSession {
        return DB::transaction(function () use ($userId, $careerPathId, $cvId, $skillIds) {
            $initialSnapshot = [];

            foreach ($skillIds as $skillId) {
                $userSkill = UserSkill::query()
                    ->where('UserId', $userId)
                    ->where('SkillId', $skillId)
                    ->first();

                $initialSnapshot[] = [
                    'skill_id' => $skillId,
                    'initial_level' => $userSkill?->ProficiencyLevel ?? 1.0,
                    'confidence_score' => (float) ($userSkill?->ConfidenceScore ?? 0.50),
                    'source' => $userSkill?->Source ?? 'default',
                ];
            }

            $session = AssessmentSession::query()->create([
                'UserID' => $userId,
                'CvID' => $cvId,
                'CareerPathID' => $careerPathId,
                'Status' => 'in_progress',
                'InitialSkillsSnapshotJson' => $initialSnapshot,
                'StartedAt' => now(),
            ]);

            foreach ($initialSnapshot as $item) {
                AssessmentSkillSession::query()->create([
                    'AssessmentSessionID' => $session->AssessmentSessionID,
                    'SkillID' => $item['skill_id'],
                    'InitialLevel' => $item['initial_level'],
                    'CurrentEstimatedLevel' => $item['initial_level'],
                    'QuestionCount' => 0,
                    'Status' => 'in_progress',
                ]);
            }

            return $session->load('skillSessions');
        });
    }
}
