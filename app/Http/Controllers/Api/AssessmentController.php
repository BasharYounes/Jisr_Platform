<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAssessmentRequest;
use App\Models\AssessmentQuestionAttempt;
use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\Skill;
use App\Models\UserSkill;
use App\Services\Assessment\AssessmentCompletionService;
use App\Services\Assessment\AssessmentSessionService;
use App\Services\Assessment\QuestionSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Support\ApiResponse;
use App\Services\Assessment\AssessmentTelemetryService;
use App\Models\QuestionBank;
use App\Services\Assessment\AssessmentInsightService;

class AssessmentController extends Controller
{
    public function __construct(
        private readonly AssessmentSessionService $assessmentSessionService,
        private readonly QuestionSelectionService $questionSelectionService,
        private readonly AssessmentCompletionService $assessmentCompletionService,
        private readonly AssessmentTelemetryService $telemetryService,
        private readonly AssessmentInsightService $assessmentInsightService,
    ) {
    }

    public function store(CreateAssessmentRequest $request): JsonResponse
    {
        $session = $this->assessmentSessionService->create(
            userId: $request->user()->id,
            careerPathId: (int) $request->career_path_id,
            cvId: $request->cv_id ? (int) $request->cv_id : null,
            skillIds: $request->skill_ids
        );

        return ApiResponse::success('Assessment session created successfully.', $session);
    }

   public function nextQuestion(AssessmentSession $session, Skill $skill): JsonResponse
    {
        $skillSession = AssessmentSkillSession::query()
            ->with([
                'assessmentSession',
                'questionAttempts.questionBank',
            ])
            ->where('AssessmentSessionID', $session->AssessmentSessionID)
            ->where('SkillID', $skill->id)
            ->first();

        if (!$skillSession) {
            return ApiResponse::error('Skill session not found.', 404);
        }

        $skillSession = $this->assessmentCompletionService
            ->completeSkillSessionIfEligible($skillSession);

        $skillSession->load([
            'assessmentSession',
            'questionAttempts.questionBank',
        ]);

        $completionReason = $this->assessmentCompletionService
            ->resolveCompletionReason($skillSession);

        if ($this->assessmentCompletionService->shouldStopAsking($skillSession)) {
            if ($completionReason === 'needs_review_limit_reached') {
                return ApiResponse::success(
                    'This skill assessment requires review before a final result can be issued.',
                    [
                        'status' => AssessmentSkillSession::STATUS_NEEDS_REVIEW,
                        'completion_reason' => $completionReason,
                        'can_continue' => false,
                        'final_result_available' => false,
                        'message_ar' => 'لا يمكن إصدار نتيجة نهائية الآن لأن عددًا من الإجابات يحتاج إلى مراجعة.',
                    ]
                );
            }

            return ApiResponse::success(
                'This skill assessment is already completed.',
                [
                    'status' => AssessmentSkillSession::STATUS_COMPLETED,
                    'completion_reason' => $completionReason,
                    'can_continue' => false,
                    'final_result_available' => true,
                    'final_level' => $skillSession->FinalLevel,
                    'confidence_score' => $skillSession->ConfidenceScore,
                ]
            );
        }

        $question = $this->questionSelectionService
            ->selectNextQuestion($skillSession);

        if (!$question) {
            return ApiResponse::error('No more questions available for this skill.', 404);
        }

        $attempt = AssessmentQuestionAttempt::query()->create([
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
            'QuestionID' => $question->QuestionID,
            'QuestionLevel' => $question->Level,
            'AskedAt' => now(),
            'LlmEvaluationStatus' => 'pending',
        ]);

        return ApiResponse::success('Next question retrieved successfully.', [
            'attempt_id' => $attempt->AssessmentQuestionAttemptID,
            'question_id' => $question->QuestionID,
            'question_text' => $question->QuestionText,
            'question_level' => $question->Level,
            'skill_id' => $skill->id,
            'skill_name' => $skill->Name ?? $skill->name,
        ]);
    }

    public function complete(AssessmentSession $session): JsonResponse
    {
        if ($session->UserID !== auth()->id()) {
            return ApiResponse::error('Unauthorized access to this assessment session.', 403);
        }

        $completionReasons = [];

        DB::transaction(function () use ($session, &$completionReasons) {
            $skillSessions = $session->skillSessions()
                ->with('attempts.questionBank')
                ->get();

            foreach ($skillSessions as $skillSession) {
                $skillSession = $this->assessmentCompletionService
                    ->completeSkillSessionIfEligible($skillSession);

                $completionReasons[$skillSession->AssessmentSkillSessionID] =
                    $this->assessmentCompletionService->resolveCompletionReason($skillSession);

                if (
                    $skillSession->Status === AssessmentSkillSession::STATUS_COMPLETED
                    && $skillSession->FinalLevel !== null
                ) {
                    $userSkill = UserSkill::query()->firstOrNew([
                        'UserId' => auth()->id(),
                        'SkillId' => $skillSession->SkillID,
                    ]);

                    $protectedStatuses = [
                        UserSkill::STATUS_CODE_TESTED,
                        UserSkill::STATUS_SUPERVISOR_VERIFIED,
                        UserSkill::STATUS_COMPANY_VERIFIED,
                    ];

                    $hasStrongerVerification = $userSkill->exists
                        && in_array(
                            $userSkill->VerificationStatus,
                            $protectedStatuses,
                            true
                        );

                    $userSkill->ProficiencyLevel = max(
                        1,
                        min(5, (int) round((float) $skillSession->FinalLevel))
                    );

                    $userSkill->ConfidenceScore = (float) (
                        $skillSession->ConfidenceScore ?? 0.5
                    );

                    if (! $hasStrongerVerification) {
                        $userSkill->Source = 'ai_assessment';
                        $userSkill->Verified = false;
                        $userSkill->VerificationStatus = UserSkill::STATUS_AI_ESTIMATED;
                        $userSkill->VerifiedAt = null;
                        $userSkill->VerifiedBy = null;
                    }

                    $userSkill->save();
                }
            }

            $freshSkillSessions = $session->skillSessions()
                ->with('attempts.questionBank')
                ->get();

            $sessionStatus = $this->resolveAssessmentSessionStatus($freshSkillSessions);

            $finalResults = $freshSkillSessions
                ->map(function ($item) {
                    $topicSummary = $this->buildTopicCoverageSummary($item);

                    $finalResultAvailable =
                        $item->Status === AssessmentSkillSession::STATUS_COMPLETED
                        && $item->FinalLevel !== null;

                    return array_merge([
                        'skill_id' => $item->SkillID,
                        'initial_level' => $item->InitialLevel,
                        'final_level' => $finalResultAvailable
                            ? $item->FinalLevel
                            : null,
                        'confidence_score' => $finalResultAvailable
                            ? $item->ConfidenceScore
                            : null,
                        'status' => $item->Status,
                        'final_result_available' => $finalResultAvailable,
                    ], $topicSummary);
                })
                ->toArray();

            $session->update([
                'Status' => $sessionStatus,
                'CompletedAt' => $sessionStatus === AssessmentSession::STATUS_COMPLETED
                    ? now()
                    : null,

                /*
                * لا نخزن FinalResultsJson كأنها نتيجة نهائية
                * إذا ما زالت هناك مهارات in_progress.
                */
                'FinalResultsJson' => $sessionStatus === AssessmentSession::STATUS_IN_PROGRESS
                    ? null
                    : $finalResults,
            ]);
        });

        $freshSession = $session->fresh([
            'skillSessions.attempts.questionBank',
        ]);

        foreach ($freshSession->skillSessions as $skillSession) {
            $completionReason = $completionReasons[$skillSession->AssessmentSkillSessionID]
                ?? $this->assessmentCompletionService->resolveCompletionReason($skillSession);

            $topicSummary = $this->buildTopicCoverageSummary($skillSession);

            $eventType = match ($skillSession->Status) {
                AssessmentSkillSession::STATUS_COMPLETED => 'skill_session_completed',
                AssessmentSkillSession::STATUS_NEEDS_REVIEW => 'skill_session_needs_review',
                default => 'skill_session_not_completed',
            };

            $trustedAttemptsQuery = $skillSession->attempts()
                ->where('LlmEvaluationStatus', 'completed')
                ->whereNotNull('NormalizedScore');

            $this->telemetryService->record([
                'assessment_session_id' => $skillSession->AssessmentSessionID ?? null,
                'assessment_skill_session_id' => $skillSession->AssessmentSkillSessionID,
                'event_type' => $eventType,
                'level_after' => $skillSession->FinalLevel ?? null,
                'confidence_score' => $skillSession->ConfidenceScore ?? null,
                'payload' => array_merge([
                    'completion_reason' => $completionReason,
                    'status' => $skillSession->Status,

                    'answered_question_count' => $skillSession->attempts()
                        ->whereNotNull('AnsweredAt')
                        ->count(),

                    'trusted_question_count' => $trustedAttemptsQuery->count(),

                    'needs_review_question_count' => $skillSession->attempts()
                        ->where('LlmEvaluationStatus', 'needs_review')
                        ->count(),

                    'tested_levels' => $trustedAttemptsQuery
                        ->pluck('QuestionLevel')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),

                    'average_score' => $trustedAttemptsQuery->avg('NormalizedScore'),
                    'min_score' => $trustedAttemptsQuery->min('NormalizedScore'),
                    'max_score' => $trustedAttemptsQuery->max('NormalizedScore'),
                    'score_variance' => null,
                ], $topicSummary),
            ]);
        }

        $message = match ($freshSession->Status) {
            AssessmentSession::STATUS_COMPLETED =>
                'Assessment session completed successfully.',

            AssessmentSession::STATUS_NEEDS_REVIEW =>
                'Assessment session requires review before final results can be issued.',

            default =>
                'Assessment session is not ready to be completed yet.',
        };

        return ApiResponse::success($message, [
            'session_id' => $freshSession->AssessmentSessionID,
            'status' => $freshSession->Status,
            'completed_at' => $freshSession->CompletedAt,
            'final_results_available' => $freshSession->Status === AssessmentSession::STATUS_COMPLETED,
            'has_skills_needing_review' => $freshSession->skillSessions
                ->contains(fn ($skillSession) => $skillSession->Status === AssessmentSkillSession::STATUS_NEEDS_REVIEW),
            'skills' => $freshSession->skillSessions->map(function ($skillSession) {
                return [
                    'skill_session_id' => $skillSession->AssessmentSkillSessionID,
                    'skill_id' => $skillSession->SkillID,
                    'status' => $skillSession->Status,
                    'final_level' => $skillSession->FinalLevel,
                    'confidence_score' => $skillSession->ConfidenceScore,
                    'question_count' => $skillSession->QuestionCount,
                ];
            }),
        ]);
    }

    private function buildTopicCoverageSummary(AssessmentSkillSession $skillSession): array
    {
        $skillSession->loadMissing('attempts.questionBank');

        $testedTopics = $skillSession->attempts
            ->pluck('questionBank.Topic')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $availableTopicCount = QuestionBank::query()
            ->where('SkillID', $skillSession->SkillID)
            ->where('IsActive', true)
            ->whereNotNull('Topic')
            ->distinct()
            ->count('Topic');

        $topicCoverageRatio = $availableTopicCount > 0
            ? round(count($testedTopics) / $availableTopicCount, 2)
            : null;

        return [
            'tested_topics' => $testedTopics,
            'topic_count' => count($testedTopics),
            'available_topic_count' => $availableTopicCount,
            'topic_coverage_ratio' => $topicCoverageRatio,
        ];
    }

    public function summary(AssessmentSession $session): JsonResponse
    {
        if ($session->UserID !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized access to this assessment session.',
            ], 403);
        }

        $session->load([
            'careerPath',
            'skillSessions.skill',
            'skillSessions.questionAttempts.answer',
            'skillSessions.questionAttempts.questionBank',
        ]);

        /*
        * مهم:
        * نمر على skill sessions قبل بناء الملخص حتى نحفظ أي حالة وصلت إلى:
        * completed أو needs_review
        */
        $session->skillSessions->each(function ($skillSession) {
            $this->assessmentCompletionService
                ->completeSkillSessionIfEligible($skillSession);
        });

        $session->refresh()->load([
            'careerPath',
            'skillSessions.skill',
            'skillSessions.questionAttempts.answer',
            'skillSessions.questionAttempts.questionBank',
        ]);

        $finalResultsBySkillId = collect($session->FinalResultsJson ?? [])
            ->keyBy('skill_id');

        $skills = $session->skillSessions->map(function ($skillSession) use ($finalResultsBySkillId) {
            $finalResult = $finalResultsBySkillId->get($skillSession->SkillID, []);

            $completionReason = $this->assessmentCompletionService
                ->resolveCompletionReason($skillSession);

            $isCompleted = $skillSession->Status === AssessmentSkillSession::STATUS_COMPLETED
                && $skillSession->FinalLevel !== null;

            $isNeedsReview = $skillSession->Status === AssessmentSkillSession::STATUS_NEEDS_REVIEW;

            $finalResultAvailable = $isCompleted && ! $isNeedsReview;

            $answeredCount = $skillSession->questionAttempts
                ->filter(fn ($attempt) => $attempt->AnsweredAt !== null)
                ->count();

            $completedEvaluationCount = $skillSession->questionAttempts
                ->filter(fn ($attempt) => $attempt->LlmEvaluationStatus === 'completed')
                ->count();

            $needsReviewEvaluationCount = $skillSession->questionAttempts
                ->filter(fn ($attempt) => $attempt->LlmEvaluationStatus === 'needs_review')
                ->count();

            $pendingEvaluationCount = $skillSession->questionAttempts
                ->filter(fn ($attempt) => $attempt->LlmEvaluationStatus === 'pending')
                ->count();

            $failedEvaluationCount = $skillSession->questionAttempts
                ->filter(fn ($attempt) => $attempt->LlmEvaluationStatus === 'failed')
                ->count();

            $insights = $isNeedsReview
                ? [
                    'level_label' => 'قيد المراجعة',
                    'confidence_label' => 'غير متاحة',
                    'coverage_label' => 'تحتاج مراجعة',
                    'strength_topics' => [],
                    'improvement_topics' => [],
                    'summary_message' => 'لا يمكن إصدار نتيجة نهائية لهذه المهارة لأن عددًا من الإجابات يحتاج إلى مراجعة.',
                ]
                : $this->assessmentInsightService
                    ->buildForSkillSession($skillSession, $finalResult);

            return [
                'skill_session_id' => $skillSession->AssessmentSkillSessionID,
                'skill_id' => $skillSession->SkillID,
                'skill_name' => $skillSession->skill?->name,

                'status' => $skillSession->Status,
                'completion_reason' => $completionReason,
                'can_continue' => $skillSession->Status === AssessmentSkillSession::STATUS_IN_PROGRESS,
                'final_result_available' => $finalResultAvailable,

                'message_ar' => $isNeedsReview
                    ? 'هذه المهارة تحتاج مراجعة قبل إصدار نتيجة نهائية.'
                    : null,

                'initial_level' => (float) $skillSession->InitialLevel,
                'current_level' => (float) $skillSession->CurrentEstimatedLevel,

                'final_level' => $finalResultAvailable
                    ? (float) $skillSession->FinalLevel
                    : null,

                'confidence_score' => $finalResultAvailable
                    ? (float) $skillSession->ConfidenceScore
                    : null,

                'question_count' => $skillSession->QuestionCount,

                'review_summary' => [
                    'answered_questions' => $answeredCount,
                    'completed_evaluations' => $completedEvaluationCount,
                    'needs_review_evaluations' => $needsReviewEvaluationCount,
                    'pending_evaluations' => $pendingEvaluationCount,
                    'failed_evaluations' => $failedEvaluationCount,
                    'trusted_question_count' => $skillSession->QuestionCount,
                ],

                'tested_topics' => $finalResultAvailable
                    ? ($finalResult['tested_topics'] ?? [])
                    : [],

                'topic_count' => $finalResultAvailable
                    ? ($finalResult['topic_count'] ?? 0)
                    : 0,

                'available_topic_count' => $finalResultAvailable
                    ? ($finalResult['available_topic_count'] ?? 0)
                    : 0,

                'topic_coverage_ratio' => $finalResultAvailable
                    ? ($finalResult['topic_coverage_ratio'] ?? null)
                    : null,

                'insights' => $insights,

                'attempts' => $skillSession->questionAttempts->map(function ($attempt) {
                    $evaluationJson = is_array($attempt->EvaluationJson)
                        ? $attempt->EvaluationJson
                        : (json_decode($attempt->EvaluationJson ?? '[]', true) ?: []);

                    return [
                        'attempt_id' => $attempt->AssessmentQuestionAttemptID,
                        'question_id' => $attempt->QuestionID,
                        'question_text' => $attempt->questionBank?->QuestionText,
                        'question_level' => $attempt->QuestionLevel,
                        'question_topic' => $attempt->questionBank?->Topic,

                        'llm_evaluation_status' => $attempt->LlmEvaluationStatus,
                        'needs_review' => $attempt->LlmEvaluationStatus === 'needs_review',

                        'normalized_score' => $attempt->NormalizedScore !== null
                            ? (float) $attempt->NormalizedScore
                            : null,

                        'feedback' => $attempt->FeedbackText,

                        'validation' => $evaluationJson['validation'] ?? null,
                        'validation_warnings' => data_get(
                            $evaluationJson,
                            'validation.warnings',
                            []
                        ),

                        'answered_at' => $attempt->AnsweredAt,
                    ];
                }),
            ];
        });

        return ApiResponse::success('Assessment summary retrieved successfully.', [
            'session_id' => $session->AssessmentSessionID,
            'status' => $session->Status,
            'career_path' => $session->careerPath?->Name,
            'started_at' => $session->StartedAt,
            'completed_at' => $session->CompletedAt,

            'has_skills_needing_review' => $skills
                ->contains(fn ($skill) => $skill['status'] === AssessmentSkillSession::STATUS_NEEDS_REVIEW),

            'review_required_skill_count' => $skills
                ->filter(fn ($skill) => $skill['status'] === AssessmentSkillSession::STATUS_NEEDS_REVIEW)
                ->count(),

            'skills' => $skills,
        ]);
    }

    private function resolveAssessmentSessionStatus($skillSessions): string
    {
        $hasInProgress = $skillSessions->contains(
            fn ($skillSession) => $skillSession->Status === AssessmentSkillSession::STATUS_IN_PROGRESS
        );

        if ($hasInProgress) {
            return AssessmentSession::STATUS_IN_PROGRESS;
        }

        $hasNeedsReview = $skillSessions->contains(
            fn ($skillSession) => $skillSession->Status === AssessmentSkillSession::STATUS_NEEDS_REVIEW
        );

        if ($hasNeedsReview) {
            return AssessmentSession::STATUS_NEEDS_REVIEW;
        }

        return AssessmentSession::STATUS_COMPLETED;
    }
}

/*
*/
