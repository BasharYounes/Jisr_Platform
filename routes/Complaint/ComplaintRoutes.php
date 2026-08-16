<?php

use App\Http\Controllers\Complaints\ComplaintController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'role:student|company|supervisor',
    'throttle:10,1',
])
    ->prefix('complaints')
    ->controller(ComplaintController::class)
    ->group(function (): void {
        Route::post('/', 'store');
    });
