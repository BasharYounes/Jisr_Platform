<?php

use App\Http\Controllers\Matching\MatchingController;
use Illuminate\Support\Facades\Route;



Route::get('/opportunities/{id}/top-candidates', [MatchingController::class, 'topCandidates']);
