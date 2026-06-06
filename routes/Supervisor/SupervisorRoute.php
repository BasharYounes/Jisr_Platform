<?php

use App\Http\Controllers\ProjectAssignmentController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\ProjectTemplateController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProjectEvaluationController;

Route::middleware('auth:sanctum')->prefix('supervisor')->group(function () {
    // Create project template
    Route::post(
        'project-templates',
        [ProjectTemplateController::class, 'create']
    );
    // Project assignment
    Route::post(
        'project-assignments',
        [ProjectAssignmentController::class, 'assignProject']
    );
    // List all project assignments
    Route::get(
        'project-assignments/all',
        [ProjectAssignmentController::class, 'index']
    );
    // Get project assignment details
    Route::get(
        'project-assignments/{projectAssignment}',
        [ProjectAssignmentController::class, 'show']
    );
    // Create project task under a project template
    Route::post(
        'project-templates/{projectTemplate}/tasks',
        [ProjectTaskController::class, 'store']
    );
    // Assign task to student
    Route::patch(
        'assignment-tasks/{projectAssignmentTask}/assign-student',
        [ProjectAssignmentController::class, 'assignTaskToStudent']
    );

    // Student task workflow
    Route::patch(
        'assignment-tasks/{projectAssignmentTask}/start',
        [ProjectAssignmentController::class, 'startTask']
    );

    // Submit student task
    Route::patch(
        'assignment-tasks/{projectAssignmentTask}/submit',
        [ProjectAssignmentController::class, 'submitTask']
    );

    // Supervisor task workflow
    Route::patch(
        'assignment-tasks/{projectAssignmentTask}/start-review',
        [ProjectAssignmentController::class, 'startTaskReview']
    );

    // Approve student task
    Route::patch(
        'assignment-tasks/{projectAssignmentTask}/approve',
        [ProjectAssignmentController::class, 'approveTask']
    );

    // Request revision for student task
    Route::patch(
        'assignment-tasks/{projectAssignmentTask}/request-revision',
        [ProjectAssignmentController::class, 'requestTaskRevision']
    );

    // Final project evaluation
    Route::post(
        'project-assignments/{projectAssignment}/evaluation',
        [ProjectEvaluationController::class, 'store']
    );

    // Get project evaluation
    Route::get(
        'project-evaluations/{projectEvaluation}',
        [ProjectEvaluationController::class, 'show']
    );

    // Approve project evaluation
    Route::patch(
        'project-evaluations/{projectEvaluation}/approve',
        [ProjectEvaluationController::class, 'approve']
    );
});
