<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MarketAnalysis\MarketDataQualityService;
use Illuminate\Http\JsonResponse;

final class MarketAnalysisDashboardController extends Controller
{
    public function __invoke(
        MarketDataQualityService $qualityService
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Market analysis dashboard retrieved successfully',
            'data' => $qualityService->getSummary(),
        ]);
    }
}
