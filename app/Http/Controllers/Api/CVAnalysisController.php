<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CV;
use App\Models\CVAnalysis;
use App\Models\CVExtractedSkill;
use App\Models\UserSkill;
use App\Services\AI\SkillExtractionService;
use App\Services\CV\CVTextExtractionService;
use App\Services\Skills\SkillNormalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\ApiResponse;

class CVAnalysisController extends Controller
{
    public function __construct(
        private readonly CVTextExtractionService $textExtractionService,
        private readonly SkillExtractionService $skillExtractionService,
        private readonly SkillNormalizationService $skillNormalizationService
    ) {
    }

    public function analyze(CV $cv): JsonResponse
    {
        $absolutePath = Storage::disk('public')->path($cv->FileUrl);
        $text = $this->textExtractionService->extractFromPath($absolutePath);

        if (blank($text)) {
            return  ApiResponse::error('Could not extract text from the CV.', 422);
        }

        try {
            $extractionResult = $this->skillExtractionService->extractSkills($text, 'Backend Developer');
        } catch (\Throwable $e) {
            return ApiResponse::error('AI analysis failed.', 502, $e->getMessage());
        }
        $skills = $extractionResult['skills'] ?? [];

        $normalized = $this->skillNormalizationService->normalizeMany($skills);

        $analysis = DB::transaction(function () use ($cv, $skills, $normalized) {
            $analysis = CVAnalysis::query()->create([
                'CvId' => $cv->CvID,
                'ExtractedSkillsJson' => $skills,
                'MissingCriteriaJson' => [],
                'OverallScore' => 0,
                'AiModelVersion' => 'extraction-v1',
                'AnalyzedAt' => now(),
            ]);

            foreach ($skills as $index => $item) {
                $match = $normalized[$index] ?? null;

                $skillId = $match['skill_id'] ?? null;

                CVExtractedSkill::query()->create([
                    'CVAnalysisID' => $analysis->CVAnalysisID,
                    'SkillID' => $skillId,
                    'RawSkillName' => $item['skill_name'] ?? '',
                    'EvidenceText' => $item['evidence'] ?? null,
                    'InitialLevel' => $item['initial_level'] ?? 0,
                    'ConfidenceScore' => $item['confidence'] ?? 0,
                    'ExtractionSource' => 'llm',
                ]);

                if ($skillId) {
                    UserSkill::query()->updateOrCreate(
                        [
                            'UserId' => $cv->UserId,
                            'SkillId' => $skillId,
                        ],
                        [
                            'ProficiencyLevel' => max(1, min(5, (int) round((float) ($item['initial_level'] ?? 1)))),
                            'ConfidenceScore' => (float) ($item['confidence'] ?? 0.5),
                            'Source' => 'cv_analysis',
                            'Verified' => false,
                        ]
                    );
                }
            }

            return $analysis;
        });

        return ApiResponse::success('CV analyzed successfully.', [
            'analysis_id' => $analysis->CVAnalysisID,
            'cv_id' => $cv->CvID,
            'skills' => collect($skills)->map(function ($skill, $index) use ($normalized) {
                return array_merge($skill, ['skill_id' => $normalized[$index]['skill_id'] ?? null]);
            })->values()->toArray(),
            // 'skill_ids' => $analysis->extractedSkills()->distinct()->pluck('SkillID')->values()->toArray(),
        ]);

    }


    public function show(CV $cv): JsonResponse
    {
        if ($cv->UserId !== auth()->id()) {
            return ApiResponse::error('You are not authorized to view this CV.', 403);
        }

        $analysis = CVAnalysis::query()
            ->with('extractedSkills.skill')
            ->where('CvId', $cv->CvID)
            ->latest('CVAnalysisID')
            ->first();

        if (!$analysis) {
            return ApiResponse::error('No analysis found for this CV.', 404);
        }

        return ApiResponse::success('CV analysis retrieved successfully.', $analysis);
    }
}
