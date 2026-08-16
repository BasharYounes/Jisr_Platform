<?php

use App\Http\Controllers\ProjectAssignmentController;
use App\Http\Controllers\ProjectEvaluationController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\ProjectTemplateController;
use App\Http\Controllers\Supervisor\ProjectAssignmentEvaluationController;
use App\Http\Controllers\Supervisor\ProjectAssignmentSupervisorController;
use App\Http\Controllers\Supervisor\ProjectEvaluationAppealReviewController;
use App\Http\Controllers\Supervisor\ProjectTemplateApplicationController;
use App\Http\Controllers\Supervisor\SupervisorAccessController;
use App\Http\Controllers\Supervisor\SupervisorDiscoveryController;
use App\Http\Controllers\Supervisor\SupervisorLeadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('supervisor')->group(function () {
    // Create project template
    Route::post(
        'project-templates',
        [ProjectTemplateController::class, 'create']
    );
    Route::patch(
        'project-templates/{projectTemplate}',
        [ProjectTemplateController::class, 'update']
    );

    // Project template applications
    Route::get(
        'project-templates/{projectTemplate}/applications',
        [ProjectTemplateApplicationController::class, 'index']
    );
    Route::get(
        'project-template-applications/{projectTemplateApplication}',
        [ProjectTemplateApplicationController::class, 'show']
    );
    Route::get(
        'project-assignments/{projectAssignment}/active-students',
        [ProjectAssignmentController::class, 'activeStudents']
    );
    Route::post(
        'project-template-applications/{projectTemplateApplication}/accept',
        [ProjectTemplateApplicationController::class, 'accept']
    );
    Route::post(
        'project-template-applications/{projectTemplateApplication}/reject',
        [ProjectTemplateApplicationController::class, 'reject']
    );

    // Project assignment
    Route::post(
        'project-assignments',
        [ProjectAssignmentController::class, 'assignProject']
    );

    // List all project assignments for the authenticated supervisor
    Route::get(
        'project-assignments/all',
        [ProjectAssignmentController::class, 'index']
    );

    // Supervisor lead discovery: assignments managed by same-specialization supervisors
    Route::get(
        'lead/project-assignments',
        [SupervisorDiscoveryController::class, 'leadAssignments']
    )->middleware('role:supervisor_lead');

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
        'project-assignments/{projectAssignment}/students/{student}/evaluation',
        [ProjectEvaluationController::class, 'store']
    );

    // Supervisor lead discovery: evaluations in current specialization
    Route::get(
        'project-evaluations',
        [SupervisorDiscoveryController::class, 'leadEvaluations']
    )->middleware('role:supervisor_lead');

    // Normal supervisor discovery: only own evaluations
    Route::get(
        'my-project-evaluations',
        [SupervisorDiscoveryController::class, 'myEvaluations']
    )->middleware('role:supervisor');

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

    Route::post(
        'project-evaluations/{projectEvaluation}/revision-requests',
        [ProjectEvaluationController::class, 'requestRevision']
    )->middleware('role:supervisor_lead');

    // Supervisor lead: list supervisors in the same specialization
    Route::get(
        'supervisors',
        [SupervisorLeadController::class, 'index']
    )->middleware('role:supervisor_lead');

    Route::patch(
        'project-evaluations/{projectEvaluation}',
        [ProjectEvaluationController::class, 'update']
    )->middleware('role:supervisor');

    Route::post(
        'project-evaluations/{projectEvaluation}/resubmit',
        [ProjectEvaluationController::class, 'resubmit']
    )->middleware('role:supervisor');

    Route::prefix('evaluation-appeals')
        ->middleware('role:supervisor_lead')
        ->controller(
            ProjectEvaluationAppealReviewController::class
        )
        ->group(function (): void {
            Route::get('/', 'index');

            Route::get(
                '/{projectEvaluationAppeal}',
                'show'
            );

            Route::patch(
                '/{projectEvaluationAppeal}/review',
                'review'
            );
        });

    Route::patch(
        'project-assignments/{projectAssignment}/supervisor',
        [ProjectAssignmentSupervisorController::class, 'update']
    )->middleware('role:supervisor_lead');

    Route::post(
        'supervisors/{supervisor}/block',
        [SupervisorAccessController::class, 'block']
    )->middleware('role:supervisor_lead');

    Route::post(
        'supervisors/{supervisor}/unblock',
        [SupervisorAccessController::class, 'unblock']
    )->middleware('role:supervisor_lead');

    Route::get(
        'project-assignments/{projectAssignment}/evaluations',
        [ProjectAssignmentEvaluationController::class, 'index']
    )->middleware('role:supervisor');

    Route::get(
        'project-assignments/{projectAssignment}/evaluations/summary',
        [ProjectAssignmentEvaluationController::class, 'summary']
    )->middleware('role:supervisor');
});
