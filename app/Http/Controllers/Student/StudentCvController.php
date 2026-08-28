<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Student\StudentCvAnalysisResource;
use App\Http\Resources\Student\StudentCvResource;
use App\Models\CV;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentCvController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cvs = CV::query()
            ->where('UserId', $request->user()->id)
            ->with([
                'latestAnalysis' => fn ($query) => $query
                    ->withCount('extractedSkills'),
            ])
            ->orderByDesc('UploadedAt')
            ->orderByDesc('CvID')
            ->get();

        return ApiResponse::success('Student CVs retrieved successfully.', [
            'cvs' => StudentCvResource::collection($cvs),
            'total' => $cvs->count(),
        ]);
    }

    public function showAnalysis(Request $request, int $cvId): JsonResponse
    {
        $cv = CV::query()
            ->where('UserId', $request->user()->id)
            ->whereKey($cvId)
            ->with([
                'latestAnalysis.extractedSkills.skill',
            ])
            ->first();

        if (! $cv) {
            return ApiResponse::error('CV not found.', 404);
        }

        if (! $cv->latestAnalysis) {
            return ApiResponse::error('No analysis found for this CV.', 404);
        }

        return ApiResponse::success('CV analysis retrieved successfully.', [
            'cv' => new StudentCvResource($cv),
            'analysis' => new StudentCvAnalysisResource($cv->latestAnalysis),
        ]);
    }
}
