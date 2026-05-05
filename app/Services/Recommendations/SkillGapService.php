<?php

namespace App\Services\Recommendations;

use App\Models\AssessmentSession;
use App\Models\CareerPathSkill;

class SkillGapService
{
    public function calculateForSession(AssessmentSession $session): array
    {
        $session->load('skillSessions.skill');

        $requiredSkills = CareerPathSkill::query()
            ->with('skill')
            ->where('CareerPathID', $session->CareerPathID)
            ->get();

        return $requiredSkills->map(function ($requiredSkill) use ($session) {
            $skillSession = $session->skillSessions
                ->firstWhere('SkillID', $requiredSkill->SkillID);

            $actualLevel = $skillSession?->FinalLevel
                ?? $skillSession?->CurrentEstimatedLevel
                ?? 0;

            $requiredLevel = (float) $requiredSkill->RequiredLevel;
            $gap = max(0, $requiredLevel - (float) $actualLevel);

            return [
                'skill_id' => $requiredSkill->SkillID,
                'skill_name' => $requiredSkill->skill?->name,
                'required_level' => $requiredLevel,
                'actual_level' => (float) $actualLevel,
                'gap' => round($gap, 1),
                'priority' => $this->resolvePriority($gap),
                'status' => $gap <= 0 ? 'sufficient' : 'needs_improvement',
            ];
        })->values()->toArray();
    }

    private function resolvePriority(float $gap): string
    {
        if ($gap >= 1.5) {
            return 'high';
        }

        if ($gap >= 0.7) {
            return 'medium';
        }

        if ($gap > 0) {
            return 'low';
        }

        return 'none';
    }
}
