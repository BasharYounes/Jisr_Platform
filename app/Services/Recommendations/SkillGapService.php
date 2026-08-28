<?php

namespace App\Services\Recommendations;

use App\Models\AssessmentSession;
use App\Models\CareerPathSkill;
use App\Services\MarketAnalysis\MarketSkillDemandContextService;

class SkillGapService
{
    public function __construct(
        private readonly MarketSkillDemandContextService $marketSkillDemandContextService
    ) {}

    public function calculateForSession(AssessmentSession $session): array
    {
        $session->load('skillSessions.skill');

        /*
         * IMPORTANT:
         * A skill-gap calculation for an assessment session must be based only
         * on the skills that were actually assessed in that session.
         *
         * The career path can contain many additional/alternative skills
         * (Laravel, Node.js, .NET, Docker, REST API, ...). Treating an
         * unassessed skill as level 0 creates false gaps and contaminates the
         * learning path.
         *
         * Therefore we intersect:
         *   career-path skills ∩ session-assessed skills
         */
        $assessedSkillIds = $session->skillSessions
            ->pluck('SkillID')
            ->filter()
            ->map(fn ($skillId) => (int) $skillId)
            ->unique()
            ->values();

        if ($assessedSkillIds->isEmpty()) {
            return [];
        }

        $finalResultsBySkillId = collect($session->FinalResultsJson ?? [])
            ->keyBy(fn ($item) => (int) ($item['skill_id'] ?? 0));

        $requiredSkills = CareerPathSkill::query()
            ->with('skill')
            ->where('CareerPathID', $session->CareerPathID)
            ->whereIn('SkillID', $assessedSkillIds->all())
            ->get();

        if ($requiredSkills->isEmpty()) {
            return [];
        }

        $marketContexts = $this->marketSkillDemandContextService->getForSkills(
            careerPathId: (int) $session->CareerPathID,
            skillIds: $requiredSkills
                ->pluck('SkillID')
                ->map(fn ($skillId) => (int) $skillId)
                ->toArray()
        );

        return $requiredSkills
            ->map(function ($requiredSkill) use (
                $session,
                $finalResultsBySkillId,
                $marketContexts
            ) {
                $skillId = (int) $requiredSkill->SkillID;

                $skillSession = $session->skillSessions
                    ->first(
                        fn ($item) => (int) $item->SkillID === $skillId
                    );

                /*
                 * Defensive guard:
                 * The query above already intersects with assessed skills, so
                 * this should never happen. If relational data is inconsistent,
                 * skip the row rather than inventing a level of zero.
                 */
                if ($skillSession === null) {
                    return null;
                }

                $finalResult = $finalResultsBySkillId->get($skillId, []);

                $actualLevel = $skillSession->FinalLevel
                    ?? $skillSession->CurrentEstimatedLevel;

                /*
                 * Never fabricate a zero proficiency for a session that has no
                 * usable estimate. A missing estimate is "unknown", not 0.
                 */
                if ($actualLevel === null) {
                    return null;
                }

                $actualLevel = (float) $actualLevel;
                $requiredLevel = (float) $requiredSkill->RequiredLevel;

                $gap = max(0, $requiredLevel - $actualLevel);

                $confidenceScore = isset($finalResult['confidence_score'])
                    ? (float) $finalResult['confidence_score']
                    : (
                        $skillSession->ConfidenceScore !== null
                            ? (float) $skillSession->ConfidenceScore
                            : null
                    );

                $topicCoverageRatio = isset(
                    $finalResult['topic_coverage_ratio']
                )
                    ? (float) $finalResult['topic_coverage_ratio']
                    : null;

                return [
                    'skill_id' => $skillId,
                    'skill_name' => $requiredSkill->skill?->name,
                    'required_level' => $requiredLevel,
                    'actual_level' => $actualLevel,
                    'gap' => round($gap, 1),
                    'priority' => $this->resolvePriority($gap),
                    'status' => $gap <= 0
                        ? 'sufficient'
                        : 'needs_improvement',

                    'market' => $marketContexts[$skillId] ?? null,

                    'confidence_score' => $confidenceScore,
                    'topic_coverage_ratio' => $topicCoverageRatio,
                    'tested_topics' => (
                        $finalResult['tested_topics'] ?? []
                    ),
                    'improvement_topics' => (
                        $finalResult['improvement_topics'] ?? []
                    ),
                    'assessment_reliability' => (
                        $this->resolveAssessmentReliability(
                            confidenceScore: $confidenceScore,
                            topicCoverageRatio: $topicCoverageRatio
                        )
                    ),
                ];
            })
            ->filter()
            ->values()
            ->toArray();
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

        if (
            $confidenceScore >= 0.75
            && (
                $topicCoverageRatio === null
                || $topicCoverageRatio >= 0.60
            )
        ) {
            return 'عالية';
        }

        if ($confidenceScore >= 0.55) {
            return 'متوسطة';
        }

        return 'منخفضة';
    }
}
