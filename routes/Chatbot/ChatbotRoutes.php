<?php

use App\Http\Controllers\Student\ChatbotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:student'])
    ->prefix('student/chatbot')
    ->controller(ChatbotController::class)
    ->group(function (): void {
        Route::get('/conversations', 'index');
        Route::post('/conversations', 'store');
        Route::get('/conversations/{conversationId}', 'show')
            ->whereNumber('conversationId');
        Route::get('/conversations/{conversationId}/messages', 'messages')
            ->whereNumber('conversationId');
        Route::post('/conversations/{conversationId}/messages', 'storeMessage')
            ->whereNumber('conversationId');
        Route::delete('/conversations/{conversationId}', 'destroy')
            ->whereNumber('conversationId');
    });
