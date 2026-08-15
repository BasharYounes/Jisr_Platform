<?php

use App\Http\Controllers\Student\ProjectEvaluationAppealController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student Evaluation Appeal Discovery
|--------------------------------------------------------------------------
|
| Lists all appeals owned by the authenticated student.
| Existing per-evaluation show/store routes remain in routes/api.php.
|
*/

Route::middleware([
    'auth:sanctum',
    'role:student',
])
    ->get(
        'student/evaluation-appeals',
        [
            ProjectEvaluationAppealController::class,
            'index',
        ]
    );
