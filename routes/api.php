<?php

use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
// use App\Http\Controllers\UserController;

use App\Http\Controllers\Api\AI\AILearningPlanController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Matching\MatchingController;
use App\Http\Controllers\Api\AssessmentAnswerController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\CVAnalysisController;
use App\Http\Controllers\Api\CVController;
use App\Http\Controllers\Company\CompanyTaskApplicationController;
use App\Http\Controllers\Company\CompanyTaskController;
use App\Http\Controllers\CompanyHomeController;
use App\Http\Controllers\Student\StudentTaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Student\PortfolioProjectController;
use App\Http\Controllers\Conversations\ConversationController;
use App\Http\Controllers\Conversations\ConversationMessageController;
use App\Http\Controllers\Conversations\ConversationParticipantController;
use App\Http\Controllers\Skill\SkillController;



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

require __DIR__ . '/MetricsResultAI/MetricsRoute.php';

require __DIR__ .'/Supervisor/SupervisorRoute.php';

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
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('auth:sanctum,role:admin');
Route::post('/otp/resend',[AuthController::class,'resendOtp']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
});

// Admin
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/CompanyUnverified', [AdminController::class, 'getUnverifiedCompanies']);
    Route::post('/companiesVerify/{companyId}', [AdminController::class, 'verifyCompany']);
    Route::post('/companiesReject/{companyId}', [AdminController::class, 'rejectCompany']);
    Route::get('/companyDetails/{companyId}', [AdminController::class, 'getCompanyDetails']);
    // Route::post('/users/{id}/assign-role', [AdminController::class, 'assignRole']);
});


    //============
    //== Company
    //============

    // Profile
    Route::middleware(['auth:sanctum', 'role:company'])->prefix('company')->group(function(){
    Route::get('profile',[UserController::class,'getProfileCompany']);
    Route::post('profile/edit',[UserController::class,'editProfile']);
    });
    ///Home
    Route::get('/company/home', [CompanyHomeController::class, 'index'])->middleware(['auth:sanctum', 'role:company']);
   
    //Company Tasks Creation & Publish
    Route::middleware(['auth:sanctum', 'role:company'])->prefix('company/tasks')->controller(CompanyTaskController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{taskId}', 'show');
        Route::put('/{taskId}', 'update');
        Route::patch('/{taskId}/publish', 'publish');
    });

     // Skill 
    Route::middleware(['auth:sanctum','role:company'])->group(function () {
    Route::get('/skills', [SkillController::class, 'index']);
    });

    //Company Tasks Applications
    Route::middleware(['auth:sanctum', 'role:company'])->prefix('company/tasks')->controller(CompanyTaskApplicationController::class)->group(function () {
        Route::get('/{taskId}/applications', 'applications');
        Route::get('/applications/student/details/{applicationId}', 'show');
        Route::post('/applications/accept/{applicationId}', 'accept');
        Route::post('/applications/reject/{applicationId}', 'reject');
    });

    //Conversation
    Route::middleware('auth:sanctum')->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversationId}', [ConversationController::class, 'show']);

    Route::get('/conversations/{conversationId}/messages', [ConversationMessageController::class, 'index']);
    Route::post('/conversations/{conversationId}/messages', [ConversationMessageController::class, 'store']);

    Route::patch('/conversations/{conversationId}/read', [ConversationParticipantController::class, 'markAsRead']);
});

    //============
    //== Student
    //============
    Route::middleware(['auth:sanctum', 'role:student'])->prefix('student')->group(function(){
    Route::get('profile',[UserController::class,'getProfileStudent']);
    Route::post('profile/edit',[UserController::class,'editProfileStudent']);
    });

    // Student Tasks
    Route::middleware(['auth:sanctum', 'role:student'])->prefix('student/tasks')->controller(StudentTaskController::class)->group(function () {
        Route::get('/explore', 'explore');
        Route::get('/recommended', 'recommended');
        Route::get('/{taskId}', 'show');
        Route::post('/{taskId}/apply', 'apply');
    }); 

    // Student Portfolio Projects
    Route::middleware(['auth:sanctum', 'role:student'])->prefix('student/portfolio-projects')->controller(PortfolioProjectController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{portfolioProjectId}', 'show');
        Route::put('/{portfolioProjectId}', 'update');
        Route::delete('/{portfolioProjectId}', 'destroy');
    });