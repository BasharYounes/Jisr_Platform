<?php

namespace App\Services\Chatbot;

use App\Models\AssessmentSession;
use App\Models\UserSkill;
use App\Services\MarketAnalysis\MarketInsightsService;
use App\Services\Recommendations\LearningPathService;
use App\Services\Recommendations\SkillGapService;
use Illuminate\Support\Collection;
use Throwable;

class ChatbotSkillsMarketDataService
{
    public function __construct(
        private readonly SkillGapService $skillGapService,
        private readonly LearningPathService $learningPathService,
        private readonly MarketInsightsService $marketInsightsService,
    ) {}

    public function build(int $studentId): array
    {
        $registeredSkills = UserSkill::query()
            ->with('skill:id,name,category')
            ->where('UserId', $studentId)
            ->orderByDesc('ProficiencyLevel')
            ->orderBy('SkillId')
            ->get()
            ->filter(fn (UserSkill $userSkill) => $userSkill->skill !== null)
            ->map(fn (UserSkill $userSkill) => [
                'skill_id' => (int) $userSkill->SkillId,
                'skill_name' => (string) $userSkill->skill->name,
                'category' => $userSkill->skill->category,
                'proficiency_level' => (int) $userSkill->ProficiencyLevel,
                'confidence_score' => $userSkill->ConfidenceScore !== null
                    ? (float) $userSkill->ConfidenceScore
                    : null,
                'source' => $userSkill->Source,
            ])
            ->values();

        $session = $this->findRelevantAssessmentSession($studentId);

        if ($session === null) {
            return [
                'registered_skills' => $registeredSkills->all(),
                'assessment' => [
                    'available' => false,
                    'status' => null,
                    'career_path_id' => null,
                    'career_path_name' => null,
                ],
                'skill_gaps' => [],
                'learning_priorities' => [],
                'market' => [
                    'available' => false,
                    'total_job_postings' => 0,
                    'top_skills' => [],
                ],
            ];
        }

        $session->loadMissing('careerPath');

        $gaps = $this->safeSkillGaps($session);
        $learningPriorities = $this->safeLearningPath($session);
        $market = $this->safeMarketAnalysis((int) $session->CareerPathID);

        return [
            'registered_skills' => $registeredSkills->all(),
            'assessment' => [
                'available' => true,
                'status' => $session->Status,
                'career_path_id' => (int) $session->CareerPathID,
                'career_path_name' => $session->careerPath?->Name,
            ],
            'skill_gaps' => $gaps,
            'learning_priorities' => $learningPriorities,
            'market' => $market,
        ];
    }

    private function findRelevantAssessmentSession(int $studentId): ?AssessmentSession
    {
        $completed = AssessmentSession::query()
            ->where('UserID', $studentId)
            ->where('Status', AssessmentSession::STATUS_COMPLETED)
            ->orderByDesc('CompletedAt')
            ->orderByDesc('AssessmentSessionID')
            ->first();

        if ($completed !== null) {
            return $completed;
        }

        return AssessmentSession::query()
            ->where('UserID', $studentId)
            ->orderByDesc('StartedAt')
            ->orderByDesc('AssessmentSessionID')
            ->first();
    }

    private function safeSkillGaps(AssessmentSession $session): array
    {
        try {
            return collect($this->skillGapService->calculateForSession($session))
                ->where('gap', '>', 0)
                ->sortByDesc(fn (array $gap) => (float) ($gap['gap'] ?? 0))
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private function safeLearningPath(AssessmentSession $session): array
    {
        try {
            return collect($this->learningPathService->generate($session))
                ->values()
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private function safeMarketAnalysis(int $careerPathId): array
    {
        try {
            $result = $this->marketInsightsService->getSkillDemandByCareerPath($careerPathId);
            $topSkills = collect($result['skills'] ?? [])
                ->take((int) config('chatbot.skills_market_analysis.market_skill_limit', 10))
                ->values()
                ->all();

            return [
                'available' => ((int) ($result['total_job_postings'] ?? 0)) > 0,
                'total_job_postings' => (int) ($result['total_job_postings'] ?? 0),
                'top_skills' => $topSkills,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'available' => false,
                'total_job_postings' => 0,
                'top_skills' => [],
            ];
        }
    }
}
