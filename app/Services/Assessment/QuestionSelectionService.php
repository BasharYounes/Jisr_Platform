<?php

namespace App\Services\Assessment;

use App\Models\AssessmentSkillSession;
use App\Models\QuestionBank;
use App\Services\Assessment\AssessmentTelemetryService;

class QuestionSelectionService
{
    public function __construct(
        private AssessmentTelemetryService $telemetryService
    ) {}

    public function selectNextQuestion(AssessmentSkillSession $skillSession): ?QuestionBank
    {
        $usedQuestionIds = $skillSession->attempts()
            ->pluck('QuestionID')
            ->toArray();

        $targetLevel = $this->resolveAdaptiveLevel($skillSession);

        $usedTopics = $this->usedTopics($skillSession);

        // 1) Try exact level first with topic diversity
        $question = $this->pickQuestionWithTopicDiversity(
            query: $this->queryBase($skillSession, $usedQuestionIds)->where('Level', $targetLevel),
            usedTopics: $usedTopics
        );

        if ($question) {
            $this->recordQuestionSelectedTelemetry(
                skillSession: $skillSession,
                question: $question,
                usedQuestionIds: $usedQuestionIds,
                usedTopics: $usedTopics,
                strategy: 'exact_estimated_level'
            );

            return $question;
        }

        // 2) Fallback: nearby levels
        $fallbackLevels = $this->fallbackLevels($targetLevel);

        foreach ($fallbackLevels as $level) {
            $question = $this->pickQuestionWithTopicDiversity(
                query: $this->queryBase($skillSession, $usedQuestionIds)->where('Level', $level),
                usedTopics: $usedTopics
            );

            if ($question) {
                $this->recordQuestionSelectedTelemetry(
                    skillSession: $skillSession,
                    question: $question,
                    usedQuestionIds: $usedQuestionIds,
                    usedTopics: $usedTopics,
                    strategy: 'fallback_nearby_level'
                );

                return $question;
            }
        }

        return null;
    }

    private function resolveAdaptiveLevel(AssessmentSkillSession $skillSession): int
    {
        $currentLevel = (float) $skillSession->CurrentEstimatedLevel;

        $lastAttempt = $skillSession->attempts()
            ->whereNotNull('NormalizedScore')
            ->latest('AnsweredAt')
            ->first();

        if (! $lastAttempt) {
            return $this->resolveTargetLevel($currentLevel);
        }

        $score = (float) $lastAttempt->NormalizedScore;

        if ($score >= 0.80) {
            return min(5, (int) ceil($currentLevel) + 1);
        }

        if ($score < 0.50) {
            return max(1, (int) floor($currentLevel) - 1);
        }

        return $this->resolveTargetLevel($currentLevel);
    }

    private function queryBase(AssessmentSkillSession $skillSession, array $usedQuestionIds): \Illuminate\Database\Eloquent\Builder
    {
        $careerPathId = $skillSession->assessmentSession->CareerPathID;

        return QuestionBank::query()
            ->where('SkillID', $skillSession->SkillID)
            ->where('IsActive', true)
            ->where(function ($query) use ($careerPathId) {
                $query->where('CareerPathID', $careerPathId)
                    ->orWhereNull('CareerPathID');
            })
            ->when(! empty($usedQuestionIds), function ($query) use ($usedQuestionIds) {
                $query->whereNotIn('QuestionID', $usedQuestionIds);
            });
    }

    private function resolveTargetLevel(float $currentEstimatedLevel): int
    {
        $rounded = (int) round($currentEstimatedLevel);

        return max(1, min(5, $rounded));
    }

    private function fallbackLevels(int $targetLevel): array
    {
        $levels = [1, 2, 3, 4, 5];

        usort($levels, function ($a, $b) use ($targetLevel) {
            return abs($a - $targetLevel) <=> abs($b - $targetLevel);
        });

        return array_values(array_filter(
            $levels,
            fn ($level) => $level !== $targetLevel
        ));
    }

    /**
     * Record telemetry for a selected question.
     *
     * @param AssessmentSkillSession $skillSession
     * @param QuestionBank $question
     * @param array $usedQuestionIds
     * @param array $usedTopics
     * @param string $strategy
     */
    private function recordQuestionSelectedTelemetry(
        AssessmentSkillSession $skillSession,
        QuestionBank $question,
        array $usedQuestionIds,
        array $usedTopics,
        string $strategy
    ): void {
        $this->telemetryService->record([
            'assessment_session_id' => $skillSession->AssessmentSessionID ?? null,
            'assessment_skill_session_id' => $skillSession->AssessmentSkillSessionID ?? null,
            'question_id' => $question->QuestionID ?? null,
            'event_type' => 'question_selected',
            'level_before' => $skillSession->CurrentEstimatedLevel ?? null,
            'payload' => [
                'selected_question_level' => $question->Level ?? null,
                'difficulty_weight' => $question->DifficultyWeight ?? null,
                'selection_strategy' => $strategy,
                'current_estimated_level' => $skillSession->CurrentEstimatedLevel ?? null,
                'used_questions_count' => count($usedQuestionIds),
                'selected_topic' => $question->Topic ?? null,
                'used_topics' => $usedTopics,
                'topic_diversity_applied' => ! empty($usedTopics)
                    && ! in_array($question->Topic, $usedTopics, true),
            ],
        ]);
    }

    public function usedTopics(AssessmentSkillSession $skillSession): array
    {
        return $skillSession->questionAttempts()
            ->with('questionBank')
            ->get()
            ->pluck('questionBank.Topic')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Prefer questions from topics that have not been tested yet.
     *
     * This improves assessment fairness by avoiding repeated questions
     * from the same sub-area of a skill when other topics are available.
     */
    private function pickQuestionWithTopicDiversity($query, array $usedTopics): ?QuestionBank
    {
        $queryWithoutUsedTopics = clone $query;

        if (! empty($usedTopics)) {
            $question = $queryWithoutUsedTopics
                ->whereNotNull('Topic')
                ->whereNotIn('Topic', $usedTopics)
                ->inRandomOrder()
                ->first();

            if ($question) {
                return $question;
            }
        }

        return $query
            ->inRandomOrder()
            ->first();
    }
}
