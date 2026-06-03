<?php

namespace App\Services\Recommendations;

use App\Models\AssessmentSession;
use App\Models\CareerPathSkill;

class SkillGapService
{
    public function calculateForSession(AssessmentSession $session): array
    {
        $session->load('skillSessions.skill');

        $finalResultsBySkillId = collect($session->FinalResultsJson ?? [])
            ->keyBy('skill_id');

        $requiredSkills = CareerPathSkill::query()
            ->with('skill')
            ->where('CareerPathID', $session->CareerPathID)
            ->get();

        return $requiredSkills->map(function ($requiredSkill) use ($session, $finalResultsBySkillId) {
            $skillSession = $session->skillSessions
                ->firstWhere('SkillID', $requiredSkill->SkillID);

            $finalResult = $finalResultsBySkillId->get($requiredSkill->SkillID, []);

            $actualLevel = $skillSession?->FinalLevel
                ?? $skillSession?->CurrentEstimatedLevel
                ?? 0;

            $requiredLevel = (float) $requiredSkill->RequiredLevel;
            $gap = max(0, $requiredLevel - (float) $actualLevel);

            $confidenceScore = isset($finalResult['confidence_score'])
                ? (float) $finalResult['confidence_score']
                : ($skillSession?->ConfidenceScore !== null ? (float) $skillSession->ConfidenceScore : null);

            $topicCoverageRatio = isset($finalResult['topic_coverage_ratio'])
                ? (float) $finalResult['topic_coverage_ratio']
                : null;

            return [
                'skill_id' => $requiredSkill->SkillID,
                'skill_name' => $requiredSkill->skill?->name,
                'required_level' => $requiredLevel,
                'actual_level' => (float) $actualLevel,
                'gap' => round($gap, 1),
                'priority' => $this->resolvePriority($gap),
                'status' => $gap <= 0 ? 'sufficient' : 'needs_improvement',

                'confidence_score' => $confidenceScore,
                'topic_coverage_ratio' => $topicCoverageRatio,
                'tested_topics' => $finalResult['tested_topics'] ?? [],
                'improvement_topics' => $finalResult['improvement_topics'] ?? [],
                'assessment_reliability' => $this->resolveAssessmentReliability(
                    confidenceScore: $confidenceScore,
                    topicCoverageRatio: $topicCoverageRatio
                ),
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

    private function resolveAssessmentReliability(
        ?float $confidenceScore,
        ?float $topicCoverageRatio
    ): string {
        if ($confidenceScore === null) {
            return 'غير محددة';
        }

        if ($confidenceScore >= 0.75 && ($topicCoverageRatio === null || $topicCoverageRatio >= 0.60)) {
            return 'عالية';
        }

        if ($confidenceScore >= 0.55) {
            return 'متوسطة';
        }

        return 'منخفضة';
    }
}
