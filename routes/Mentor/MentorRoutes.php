<?php

use App\Http\Controllers\admin\AdminMentorApplicationController;
use App\Http\Controllers\Company\CompanyMentorNominationController;
use App\Http\Controllers\Mentor\MentorApplicationController;
use App\Http\Controllers\Student\StudentMentorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mentor self application
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')
    ->prefix('mentor')
    ->controller(MentorApplicationController::class)
    ->group(function (): void {
        Route::post('/application', 'store');
        Route::get('/application/me', 'showMine');
    });

/*
|--------------------------------------------------------------------------
| Company employee nominations
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:company'])
    ->prefix('company/mentor-nominations')
    ->controller(CompanyMentorNominationController::class)
    ->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
    });

/*
|--------------------------------------------------------------------------
| Admin mentor application review
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin/mentor-applications')
    ->controller(AdminMentorApplicationController::class)
    ->group(function (): void {
        Route::get('/', 'index');

        Route::get('/{mentorProfile}/cv', 'downloadCv')
            ->whereNumber('mentorProfile');

        Route::get('/{mentorProfile}', 'show')
            ->whereNumber('mentorProfile');

        Route::patch('/{mentorProfile}/approve', 'approve')
            ->whereNumber('mentorProfile');

        Route::patch('/{mentorProfile}/reject', 'reject')
            ->whereNumber('mentorProfile');
    });

/*
|--------------------------------------------------------------------------
| Student mentor discovery
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:student'])
    ->prefix('student/mentors')
    ->controller(StudentMentorController::class)
    ->group(function (): void {
        Route::get('/', 'index');

        Route::get('/{mentorProfile}', 'show')
            ->whereNumber('mentorProfile');
    });
