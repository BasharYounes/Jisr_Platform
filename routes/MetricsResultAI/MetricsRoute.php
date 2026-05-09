<?php


use Illuminate\Support\Facades\Route;
use App\Services\Assessment\AssessmentMetricsService;

Route::get('/assessment/metrics/questions', function (
    AssessmentMetricsService $metricsService
) {
    return response()->json([
        'data' => $metricsService->questionMetrics(),
    ]);
});

Route::get('/assessment/metrics/low-confidence', function (
    AssessmentMetricsService $metricsService
) {
    return response()->json([
        'data' => $metricsService->lowConfidenceEvaluations(),
    ]);
});

Route::get('/assessment/metrics/summary', function (
    AssessmentMetricsService $metricsService
) {
    return response()->json([
        'data' => $metricsService->completedSkillSessionsMetrics(),
    ]);
});
