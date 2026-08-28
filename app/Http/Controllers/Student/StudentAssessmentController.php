<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Student\StudentAssessmentDetailsResource;
use App\Http\Resources\Student\StudentAssessmentResource;
use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentAssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assessments = $this->studentAssessmentsQuery($request)
            ->with([
                'careerPath',
                'cv',
            ])
            ->orderByDesc('StartedAt')
            ->orderByDesc('AssessmentSessionID')
            ->get();

        return ApiResponse::success('Student assessments retrieved successfully.', [
            'assessments' => StudentAssessmentResource::collection($assessments),
            'total' => $assessments->count(),
        ]);
    }

    public function show(
        Request $request,
        int $assessmentSessionId
    ): JsonResponse {
        $assessment = $this->studentAssessmentsQuery($request)
            ->whereKey($assessmentSessionId)
            ->with([
                'careerPath',
                'cv',
                'skillSessions' => fn ($query) => $query
                    ->orderBy('AssessmentSkillSessionID'),
                'skillSessions.skill',
                'skillSessions.questionAttempts' => fn ($query) => $query
                    ->orderBy('AssessmentQuestionAttemptID'),
                'skillSessions.questionAttempts.questionBank',
                'skillSessions.questionAttempts.answer',
            ])
            ->first();

        if (! $assessment) {
            return ApiResponse::error('Assessment session not found.', 404);
        }

        return ApiResponse::success(
            'Assessment details retrieved successfully.',
            new StudentAssessmentDetailsResource($assessment)
        );
    }

    private function studentAssessmentsQuery(Request $request): Builder
    {
        return AssessmentSession::query()
            ->where('UserID', $request->user()->id)
            ->withCount([
                'skillSessions',
                'skillSessions as completed_skills_count' => fn ($query) => $query
                    ->where('Status', AssessmentSkillSession::STATUS_COMPLETED),
                'skillSessions as needs_review_skills_count' => fn ($query) => $query
                    ->where('Status', AssessmentSkillSession::STATUS_NEEDS_REVIEW),
            ]);
    }
}
