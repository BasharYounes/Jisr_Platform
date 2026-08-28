<?php

/*
|--------------------------------------------------------------------------
| Replace ONLY the current "Student Project Template Applications" route
| group in routes/api.php with the group below.
|--------------------------------------------------------------------------
|
| Keep static /applications/* routes before /{projectTemplate} so that
| Laravel never tries to bind "applications" as a project template ID.
|
*/

Route::middleware([
    'auth:sanctum',
    'role:student',
])
    ->prefix('student/project-templates')
    ->controller(StudentProjectTemplateController::class)
    ->group(function (): void {
        // Discovery -> choose entity -> use returned project id.
        Route::get('/', 'index');

        // Existing application history/state endpoints.
        Route::get('/applications/all', 'all');
        Route::get('/applications/pending', 'pending');
        Route::get('/applications/accepted', 'accepted');
        Route::get('/applications/rejected', 'rejected');

        // Selected-project details and mutation.
        Route::get('/{projectTemplate}', 'show')
            ->whereNumber('projectTemplate');

        Route::post('/{projectTemplate}/apply', 'apply')
            ->whereNumber('projectTemplate');
    });
