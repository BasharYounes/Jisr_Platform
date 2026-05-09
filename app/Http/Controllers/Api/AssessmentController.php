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

class AssessmentController extends Controller
{
    public function __construct(
        private readonly AssessmentSessionService $assessmentSessionService,
        private readonly QuestionSelectionService $questionSelectionService,
        private readonly AssessmentCompletionService $assessmentCompletionService,
        private readonly AssessmentTelemetryService $telemetryService,
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

        $this->assessmentCompletionService
            ->completeSkillSessionIfEligible($skillSession);

        $skillSession->refresh();

        $skillSession->load([
            'assessmentSession',
            'questionAttempts.questionBank',
        ]);

        if ($this->assessmentCompletionService->shouldStopAsking($skillSession)) {
            return ApiResponse::error('This skill assessment is already completed.', 422);
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

        DB::transaction(function () use ($session) {
            $skillSessions = $session->skillSessions()->with('attempts')->get();

            foreach ($skillSessions as $skillSession) {
                $this->assessmentCompletionService->completeSkillSessionIfEligible($skillSession);

                if ($skillSession->fresh()->FinalLevel !== null) {
                    UserSkill::query()->updateOrCreate(
                        [
                            'UserId' => $session->UserID,
                            'SkillId' => $skillSession->SkillID,
                        ],
                        [
                            'ProficiencyLevel' => max(1, min(5, (int) round((float) $skillSession->fresh()->FinalLevel))),
                            'ConfidenceScore' => (float) ($skillSession->fresh()->ConfidenceScore ?? 0.5),
                            'Source' => 'ai_assessment',
                            'Verified' => true,
                        ]
                    );
                }
            }

            $session->update([
                'Status' => 'completed',
                'CompletedAt' => now(),
                'FinalResultsJson' => $session->skillSessions()->get()->map(function ($item) {
                    return [
                        'skill_id' => $item->SkillID,
                        'initial_level' => $item->InitialLevel,
                        'final_level' => $item->FinalLevel,
                        'confidence_score' => $item->ConfidenceScore,
                        'status' => $item->Status,
                    ];
                })->toArray(),
            ]);
        });

        $this->telemetryService->record([
            'assessment_session_id' => $skillSession->AssessmentSessionID ?? null,
            'assessment_skill_session_id' => $session->skillSessions()->AssessmentSkillSessionID,

            'event_type' => 'skill_session_completed',

            'level_after' => $skillSession->FinalEstimatedLevel ?? null,
            'confidence_score' => $skillSession->ConfidenceScore ?? null,

            'payload' => [
                'completion_reason' => $completionReason ?? null,
                'question_count' => $session->skillSessions()->attempts()->count() ?? null,
                'tested_levels' => $session->skillSessions()->attempts()
                    ->pluck('questionBank.Level')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'average_score' => $session->skillSessions()->attempts()->avg('NormalizedScore'),
                'min_score' => $session->skillSessions()->attempts()->min('NormalizedScore'),
                'max_score' => $session->skillSessions()->attempts()->max('NormalizedScore'),
                'score_variance' => null,
            ],
        ]);

        return ApiResponse::success('Assessment session completed successfully.', $session->fresh('skillSessions'));
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

        return ApiResponse::success('Assessment summary retrieved successfully.', [
            'session_id' => $session->AssessmentSessionID,
            'status' => $session->Status,
            'career_path' => $session->careerPath?->Name,
            'started_at' => $session->StartedAt,
            'completed_at' => $session->CompletedAt,
            'skills' => $session->skillSessions->map(function ($skillSession) {
                return [
                    'skill_session_id' => $skillSession->AssessmentSkillSessionID,
                        'skill_id' => $skillSession->SkillID,
                        'skill_name' => $skillSession->skill?->name,
                        'initial_level' => (float) $skillSession->InitialLevel,
                        'current_level' => (float) $skillSession->CurrentEstimatedLevel,
                        'final_level' => $skillSession->FinalLevel !== null ? (float) $skillSession->FinalLevel : null,
                        'confidence_score' => $skillSession->ConfidenceScore !== null ? (float) $skillSession->ConfidenceScore : null,
                        'question_count' => $skillSession->QuestionCount,
                        'status' => $skillSession->Status,
                        'attempts' => $skillSession->questionAttempts->map(function ($attempt) {
                            return [
                                'attempt_id' => $attempt->AssessmentQuestionAttemptID,
                                'question_id' => $attempt->QuestionID,
                                'question_text' => $attempt->questionBank?->QuestionText,
                                'question_level' => $attempt->QuestionLevel,
                                'normalized_score' => $attempt->NormalizedScore !== null ? (float) $attempt->NormalizedScore : null,
                                'feedback' => $attempt->FeedbackText,
                                'answered_at' => $attempt->AnsweredAt,
                            ];
                        }),
                    ];
                }),
            ],
        );
    }
}
