<?php

use App\Http\Controllers\Complaints\ComplaintController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'role:student|company|supervisor',
])
    ->prefix('complaints')
    ->controller(ComplaintController::class)
    ->group(function (): void {
        Route::get('/mine', 'mine');

        Route::post('/', 'store')
            ->middleware('throttle:10,1');
    });
