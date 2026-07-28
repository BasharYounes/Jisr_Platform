<?php

namespace App\Http\Controllers\MarketAnalysis;

use App\Http\Controllers\Controller;
use App\Models\CareerPath;
use App\Services\MarketAnalysis\MarketInsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MarketInsightsController extends Controller
{
    public function __construct(
        private readonly MarketInsightsService $marketInsightsService
    ) {}

    /**
     * Return market skill demand insights for a specific career path.
     */
    public function skillDemand(Request $request, int $careerPathId): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $careerPath = CareerPath::query()
            ->where('CareerPathID', $careerPathId)
            ->first();

        if (! $careerPath) {
            return response()->json([
                'success' => false,
                'message' => 'Career path not found.',
                'data' => null,
            ], 404);
        }

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : null;

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : null;

        $insights = $this->marketInsightsService
            ->getSkillDemandByCareerPath(
                careerPathId: $careerPath->CareerPathID,
                from: $from,
                to: $to
            );

        return response()->json([
            'success' => true,
            'message' => 'Market skill demand insights retrieved successfully.',
            'data' => [
                'career_path' => [
                    'id' => $careerPath->CareerPathID,
                    'name' => $careerPath->Name,
                    'description' => $careerPath->Description,
                ],
                'filters' => [
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                ],
                'total_job_postings' => $insights['total_job_postings'],
                'skills' => $insights['skills']->values(),
                'skill_map' => $insights['skill_map'],
            ],
        ]);
    }

    public function trendSnapshot(Request $request, int $careerPathId): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $careerPath = CareerPath::query()
            ->where('CareerPathID', $careerPathId)
            ->first();

        if (! $careerPath) {
            return response()->json([
                'success' => false,
                'message' => 'Career path not found.',
                'data' => null,
            ], 404);
        }

        $date = $validated['date']
            ?? $this->marketInsightsService
                ->getLatestTrendSnapshotDate($careerPath->CareerPathID);

        if (! $date) {
            return response()->json([
                'success' => true,
                'message' => 'No market trend snapshot found for this career path.',
                'data' => [
                    'career_path' => [
                        'id' => $careerPath->CareerPathID,
                        'name' => $careerPath->Name,
                        'description' => $careerPath->Description,
                    ],
                    'analyzed_date' => null,
                    'total_skills' => 0,
                    'trends' => [],
                    'trend_map' => [],
                ],
            ]);
        }

        $trends = $this->marketInsightsService
            ->getTrendSnapshot(
                careerPathId: $careerPath->CareerPathID,
                analyzedDate: $date
            );

        return response()->json([
            'success' => true,
            'message' => 'Market trend snapshot retrieved successfully.',
            'data' => [
                'career_path' => [
                    'id' => $careerPath->CareerPathID,
                    'name' => $careerPath->Name,
                    'description' => $careerPath->Description,
                ],
                'analyzed_date' => $date,
                'total_skills' => $trends->count(),
                'trends' => $trends->values(),
                'trend_map' => $trends
                    ->groupBy('skill_category')
                    ->map(fn ($items) => $items->values()),
            ],
        ]);
    }

    public function skillEvidence(Request $request, int $careerPathId, int $skillId): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $careerPath = CareerPath::query()
            ->where('CareerPathID', $careerPathId)
            ->first();

        if (! $careerPath) {
            return response()->json([
                'success' => false,
                'message' => 'Career path not found.',
                'data' => null,
            ], 404);
        }

        $evidence = $this->marketInsightsService
            ->getSkillEvidence(
                careerPathId: $careerPath->CareerPathID,
                skillId: $skillId,
                limit: (int) ($validated['limit'] ?? 10)
            );

        return response()->json([
            'success' => true,
            'message' => 'Skill evidence retrieved successfully.',
            'data' => [
                'career_path' => [
                    'id' => $careerPath->CareerPathID,
                    'name' => $careerPath->Name,
                    'description' => $careerPath->Description,
                ],
                'skill_id' => $skillId,
                'total_returned' => $evidence->count(),
                'evidence' => $evidence,
            ],
        ]);
    }

    public function careerPaths(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'only_with_market_data' => ['nullable', 'boolean'],
        ]);

        $careerPaths = $this->marketInsightsService
            ->getCareerPathsForMarketAnalysis(
                onlyWithMarketData: (bool) ($validated['only_with_market_data'] ?? false)
            );

        return response()->json([
            'success' => true,
            'message' => 'Market analysis career paths retrieved successfully.',
            'data' => [
                'total' => $careerPaths->count(),
                'career_paths' => $careerPaths,
            ],
        ]);
    }
}
