<?php

use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

use App\Http\Controllers\Api\AI\AILearningPlanController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Matching\MatchingController;
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
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/verify-otp', [AuthController::class, 'verifyLoginOtp']);
Route::post('/password/forgot', [AuthController::class, 'forgetPassword']);
Route::post('/password/reset/verify-otp', [AuthController::class, 'verifyOTPresetPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('auth:admin');
Route::post('/otp/resend',[AuthController::class,'resendOtp']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
});

// Admin
Route::middleware('auth:admin')-> prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/CompanyUnverified', [AdminController::class, 'getUnverifiedCompanies']);
    Route::post('/companiesVerify/{companyId}', [AdminController::class, 'verifyCompany']);
    Route::post('/companiesReject/{companyId}', [AdminController::class, 'rejectCompany']);
    Route::get('/companyDetails/{companyId}', [AdminController::class, 'getCompanyDetails']);
    // Route::post('/users/{id}/assign-role', [AdminController::class, 'assignRole']);
});


//Company
Route::middleware('auth:sanctum')->prefix('company')->group(function(){
Route::get('profile',[UserController::class,'getProfileCompany']);
Route::post('profile/edit',[UserController::class,'editProfile']);
});


//Student
Route::middleware('auth:santum')->prefix('student')->group(function(){
Route::get('profile',[UserController::class,'getPofileStudent']);
Route::post('profile/edit',[UserController::class,'editPofile']);
});