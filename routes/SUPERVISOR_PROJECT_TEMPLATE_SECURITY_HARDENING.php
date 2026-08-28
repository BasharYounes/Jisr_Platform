<?php

/*
|--------------------------------------------------------------------------
| Security hardening for routes/Supervisor/SupervisorRoute.php
|--------------------------------------------------------------------------
|
| The current repository puts the supervisor route group behind
| auth:sanctum only. At minimum, the project-template creation/editing,
| application-review, and template-task creation endpoints must also be
| restricted to the supervisor role.
|
| Apply ->middleware('role:supervisor') to these existing routes:
|
*/

Route::post(
    'project-templates',
    [ProjectTemplateController::class, 'create']
)->middleware('role:supervisor');

Route::patch(
    'project-templates/{projectTemplate}',
    [ProjectTemplateController::class, 'update']
)->middleware('role:supervisor');

Route::get(
    'project-templates/{projectTemplate}/applications',
    [ProjectTemplateApplicationController::class, 'index']
)->middleware('role:supervisor');

Route::get(
    'project-template-applications/{projectTemplateApplication}',
    [ProjectTemplateApplicationController::class, 'show']
)->middleware('role:supervisor');

Route::post(
    'project-template-applications/{projectTemplateApplication}/accept',
    [ProjectTemplateApplicationController::class, 'accept']
)->middleware('role:supervisor');

Route::post(
    'project-template-applications/{projectTemplateApplication}/reject',
    [ProjectTemplateApplicationController::class, 'reject']
)->middleware('role:supervisor');

Route::post(
    'project-templates/{projectTemplate}/tasks',
    [ProjectTaskController::class, 'store']
)->middleware('role:supervisor');

/*
| IMPORTANT:
| Route middleware blocks non-supervisors, but ProjectTaskController::store
| should ALSO enforce ownership (creator id) before creating a task.
| See the provided replacement ProjectTaskController.php.
*/
