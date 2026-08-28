<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Request;

class StudentAssessmentDetailsResource extends StudentAssessmentResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'initial_skills_snapshot' => $this->InitialSkillsSnapshotJson ?? [],
            'final_results' => $this->FinalResultsJson ?? [],
            'skills' => $this->skillSessions->map(fn ($skillSession) => [
                'skill_session_id' => $skillSession->AssessmentSkillSessionID,
                'skill_id' => $skillSession->SkillID,
                'skill_name' => $skillSession->skill?->name,
                'status' => $skillSession->Status,
                'initial_level' => (float) $skillSession->InitialLevel,
                'current_level' => (float) $skillSession->CurrentEstimatedLevel,
                'final_level' => $skillSession->FinalLevel !== null
                    ? (float) $skillSession->FinalLevel
                    : null,
                'confidence_score' => $skillSession->ConfidenceScore !== null
                    ? (float) $skillSession->ConfidenceScore
                    : null,
                'question_count' => (int) $skillSession->QuestionCount,
                'completed_at' => $skillSession->CompletedAt?->toISOString(),
                'attempts' => $skillSession->questionAttempts->map(
                    fn ($attempt) => [
                        'attempt_id' => $attempt->AssessmentQuestionAttemptID,
                        'question' => [
                            'question_id' => $attempt->QuestionID,
                            'text' => $attempt->questionBank?->QuestionText,
                            'level' => (int) $attempt->QuestionLevel,
                            'topic' => $attempt->questionBank?->Topic,
                        ],
                        'answer' => $attempt->answer ? [
                            'text' => $attempt->answer->AnswerText,
                            'data' => $attempt->answer->AnswerJson,
                            'submitted_at' => $attempt->answer->SubmittedAt?->toISOString(),
                        ] : null,
                        'evaluation' => [
                            'status' => $attempt->LlmEvaluationStatus,
                            'needs_review' => $attempt->LlmEvaluationStatus === 'needs_review',
                            'raw_score' => $attempt->RawScore !== null
                                ? (float) $attempt->RawScore
                                : null,
                            'normalized_score' => $attempt->NormalizedScore !== null
                                ? (float) $attempt->NormalizedScore
                                : null,
                            'feedback' => $attempt->FeedbackText,
                            'engine' => $attempt->EvaluationEngine,
                            'engine_status' => $attempt->EvaluationStatus,
                            'engine_version' => $attempt->EvaluationEngineVersion,
                        ],
                        'asked_at' => $attempt->AskedAt?->toISOString(),
                        'answered_at' => $attempt->AnsweredAt?->toISOString(),
                    ]
                )->values(),
            ])->values(),
        ]);
    }
}
