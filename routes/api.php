<?php

use App\Http\Controllers\Api\AI\AILearningPlanController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Matching\MatchingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AssessmentAnswerController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\CVAnalysisController;
use App\Http\Controllers\Api\CVController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/cvs/upload', [CVController::class, 'upload']);
    Route::post('/cvs/{cv}/analyze', [CVAnalysisController::class, 'analyze']);
    Route::get('/cvs/{cv}/analysis', [CVAnalysisController::class, 'show']);

    Route::post('/assessments', [AssessmentController::class, 'store']);
    Route::post('/assessments/{session}/skills/{skill}/next-question', [AssessmentController::class, 'nextQuestion']);
    Route::post('/assessments/{session}/attempts/{attempt}/answer', [AssessmentAnswerController::class, 'submit']);
    Route::get('/assessments/{session}/attempts/{attempt}/result', [AssessmentAnswerController::class, 'result']);
    Route::post('/assessments/{session}/complete', [AssessmentController::class, 'complete']);

    Route::get('/assessments/{session}/summary', [AssessmentController::class, 'summary']);
    Route::get('/assessments/{session}/skill-gaps', [RecommendationController::class, 'skillGaps']);
    Route::get('/assessments/{session}/learning-path', [LearningController::class, 'path']);

    Route::post('/assessments/{session}/ai-learning-plan', [AILearningPlanController::class, 'generate']);
    Route::get('/assessments/{session}/ai-learning-plan/latest', [AILearningPlanController::class, 'latest']);
});
Route::get('/opportunities/{id}/top-candidates', [MatchingController::class, 'topCandidates']);

Route::get('/dev/login-as-test', function () {
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'dev@test.com'],
            ['name' => 'Dev User', 'password' => bcrypt('123456')]
        );
        $token = $user->createToken('dev-token')->plainTextToken;
        return response()->json(['token' => $token]);
    });

Route::get('/dev/test-gemini', function (\App\Services\AI\AIClientInterface $ai) {
    return $ai->generateJson(
        'Return valid JSON only.',
        'Return this exact JSON: {"status":"ok","provider":"gemini"}'
    );
});
