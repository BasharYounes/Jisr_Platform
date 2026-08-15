<?php

use App\Http\Controllers\Student\AssignedProjectTaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student Project Assignment Tasks
|--------------------------------------------------------------------------
|
| These are project tasks assigned by a supervisor through the
| ProjectAssignment workflow. They are intentionally separate from
| /api/student/tasks/*, which belongs to the Company Tasks feature.
|
*/

Route::middleware([
    'auth:sanctum',
    'role:student',
])
    ->prefix('student/project-assignment-tasks')
    ->controller(AssignedProjectTaskController::class)
    ->group(function (): void {
        Route::get('/', 'index');
    });
