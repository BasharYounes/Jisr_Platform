<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\AI\AILearningPlanController;
use App\Http\Controllers\Api\AssessmentAnswerController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\CVAnalysisController;
use App\Http\Controllers\Api\CVController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Company\CompanyTaskApplicationController;
use App\Http\Controllers\Company\CompanyTaskAssignmentController;
use App\Http\Controllers\Company\CompanyTaskController;
use App\Http\Controllers\Company\CompanyTaskProgressController;
use App\Http\Controllers\Company\CompanyTaskReviewController;
use App\Http\Controllers\Company\CompanyTaskSubmissionController;
use App\Http\Controllers\CompanyHomeController;
use App\Http\Controllers\CompanyOpportunity\CompanyOpportunityController;
use App\Http\Controllers\Conversations\ConversationController;
use App\Http\Controllers\Conversations\ConversationMessageController;
use App\Http\Controllers\Conversations\ConversationParticipantController;
use App\Http\Controllers\Matching\MatchingController;
use App\Http\Controllers\Skill\SkillController;
use App\Http\Controllers\Student\PortfolioProjectController;
use App\Http\Controllers\Student\StudentTaskApplicationController;
use App\Http\Controllers\Student\StudentTaskController;
use App\Http\Controllers\Student\StudentTaskProgressController;
use App\Http\Controllers\Student\StudentTaskSubmissionController;
use App\Http\Controllers\UserController;
use App\Models\User;
use App\Services\AI\AIClientInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes([
    'middleware' => ['auth:sanctum'],
]);

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

require __DIR__.'/MetricsResultAI/MetricsRoute.php';

require __DIR__.'/Supervisor/SupervisorRoute.php';

require __DIR__.'/Notification/NotificationsRoutes.php';

Route::get('/dev/login-as-test', function () {
    $user = User::firstOrCreate(
        ['email' => 'dev@test.com'],
        ['name' => 'Dev User', 'password' => bcrypt('123456')]
    );
    $token = $user->createToken('dev-token')->plainTextToken;

    return response()->json(['token' => $token]);
});

Route::get('/dev/test-gemini', function (AIClientInterface $ai) {
    return $ai->generateJson(
        'Return valid JSON only.',
        'Return this exact JSON: {"status":"ok","provider":"gemini"}'
    );
});
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/verify-otp', [AuthController::class, 'verifyLoginOtp']);
Route::post('/password/forgot', [AuthController::class, 'forgetPassword']);
Route::post('/password/reset/verify-otp', [AuthController::class, 'verifyOTPresetPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('auth:sanctum,role:admin');
Route::post('/otp/resend', [AuthController::class, 'resendOtp']);
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

// ============
// == Company
// ============

// Profile
Route::middleware(['auth:sanctum', 'role:company'])->prefix('company')->group(function () {
    Route::get('profile', [UserController::class, 'getProfileCompany']);
    Route::post('profile/edit', [UserController::class, 'editProfile']);
});
// /Home
Route::get('/company/home', [CompanyHomeController::class, 'index'])->middleware(['auth:sanctum', 'role:company']);
//
// CompanyTasks
// /============
// Company Tasks Creation & Publish
Route::middleware(['auth:sanctum', 'role:company'])->prefix('company/tasks')->controller(CompanyTaskController::class)->group(function () {
    Route::post('index/', 'index');
    Route::get('', 'index');
    Route::post('/', 'store');
    Route::get('/{taskId}', 'show')->whereNumber('taskId');
    Route::put('/{taskId}', 'update')->whereNumber('taskId');
    Route::patch('/{taskId}/publish', 'publish')->whereNumber('taskId');
    Route::patch('/{taskId}/close', 'close')->whereNumber('taskId');
    Route::patch('{taskId}/cancel', 'cancel')->whereNumber('taskId');
});

// Get Skill for task
Route::middleware(['auth:sanctum', 'role:company'])->group(function () {
    Route::get('/skills', [SkillController::class, 'index']);
});

// Company Tasks Applications
Route::middleware(['auth:sanctum', 'role:company'])->prefix('company/tasks')->controller(CompanyTaskApplicationController::class)->group(function () {
    Route::get('/{taskId}/applications', 'applications');
    Route::get('/applications/student/details/{applicationId}', 'show');
    Route::post('/applications/accept/{applicationId}', 'accept');
    Route::post('/applications/reject/{applicationId}', 'reject');

});

//  Tasks Assignment
Route::middleware(['auth:sanctum', 'role:company'])->prefix('company/task-assignments')->controller(CompanyTaskAssignmentController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/{assignmentId}', 'show')->whereNumber('assignmentId');
});

// Company Task Assignments Progress
Route::middleware(['auth:sanctum', 'role:company'])->prefix('company/task-assignments')->group(function () {
    Route::get('/{assignmentId}/progress', [CompanyTaskProgressController::class, 'index'])->whereNumber('assignmentId');
});

// company task submission
Route::middleware(['auth:sanctum', 'role:company'])->prefix('company/task-assignments')->controller(CompanyTaskSubmissionController::class)->group(function () {
    Route::get('/{assignmentId}/submission', 'show')->whereNumber('assignmentId');
});

// Company Review
Route::middleware(['auth:sanctum', 'role:company'])->prefix('company/tasks/review')->controller(CompanyTaskReviewController::class)->group(function () {
    Route::post('/{assignmentId}', 'store')->whereNumber('assignmentId');
    Route::get('/{assignmentId}', 'show')->whereNumber('assignmentId');
});

// /============
// Conversation
// /============
Route::middleware('auth:sanctum')->prefix('conversations')->controller(ConversationController::class)->group(function () {
    Route::get('/all', 'index');
    Route::get('/task-conversations', 'taskConversations');
    Route::get('/closed', 'closedConversations');
    Route::get('/{conversationId}', 'show');
    Route::get('/{conversationId}/messages', 'index');
    Route::post('/{conversationId}/messages', 'store');
});
// Messages
Route::middleware('auth:sanctum')->prefix('conversations/messages')->controller(ConversationMessageController::class)->group(function () {
    Route::get('/{conversationId}', 'index');
    Route::post('/{conversationId}', 'store');
    Route::post('/update/{messageId}', 'update');
});
// Marks As Read
Route::middleware('auth:sanctum')->prefix('conversations')->controller(ConversationParticipantController::class)->group(function () {
    Route::patch('/{conversationId}/read', 'markAsRead');
});

//
// Company Opportunities
// =======================
Route::middleware(['auth:sanctum', 'role:company'])->prefix('company/opportunities')->controller(CompanyOpportunityController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');

    Route::get('/{opportunityId}', 'show')->whereNumber('opportunityId');
    Route::put('/{opportunityId}', 'update')->whereNumber('opportunityId');

    Route::patch('/{opportunityId}/publish', 'publish')->whereNumber('opportunityId');
    Route::patch('/{opportunityId}/close', 'close')->whereNumber('opportunityId');
    Route::patch('/{opportunityId}/cancel', 'cancel')->whereNumber('opportunityId');

    Route::delete('/{opportunityId}', 'destroy')->whereNumber('opportunityId');
});

// ============
// == Student
// ============
Route::middleware(['auth:sanctum', 'role:student'])->prefix('student')->group(function () {
    Route::get('profile', [UserController::class, 'getProfileStudent']);
    Route::post('profile/edit', [UserController::class, 'editProfileStudent']);
});

// Get Tasks for student
Route::middleware(['auth:sanctum', 'role:student'])->prefix('student/tasks')->controller(StudentTaskApplicationController::class)->group(function () {
    Route::get('/applied', 'applied');
    Route::get('/accepted', [StudentTaskApplicationController::class, 'accepted']);
    Route::get('/rejected', [StudentTaskApplicationController::class, 'rejected']);
    Route::get('/allMyTask', [StudentTaskApplicationController::class, 'all']);
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

// student Tsasks Assignment Progress
Route::middleware(['auth:sanctum', 'role:student'])->prefix('student/task-assignments')->group(function () {
    Route::get('/{assignmentId}/progress', [StudentTaskProgressController::class, 'index'])->whereNumber('assignmentId');
    Route::post('/{assignmentId}/progress', [StudentTaskProgressController::class, 'store'])->whereNumber('assignmentId');

    // Student task submission
    Route::post('/{assignmentId}/submission', [StudentTaskSubmissionController::class, 'store'])->whereNumber('assignmentId');
    Route::get('/{assignmentId}/submission', [StudentTaskSubmissionController::class, 'show'])->whereNumber('assignmentId');
});
