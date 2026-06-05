<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;

Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
});


use App\Services\Notifications\NotificationService;
use App\Support\NotificationTypes;
use Illuminate\Http\Request;

Route::middleware('auth:sanctum')->post('/debug/send-test-notification', function (
    Request $request,
    NotificationService $notifications
) {
    abort_unless(app()->isLocal(), 404);

    $notification = $notifications->send(
        recipient: $request->user(),
        type: NotificationTypes::PROJECT_STATUS_CHANGED,
        title: 'اختبار إشعار مباشر',
        body: 'إذا ظهر هذا الإشعار في صفحة الاختبار، فالـ WebSocket يعمل بنجاح.',
        actor: $request->user(),
        related: null,
        data: [
            'screen' => 'debug_test',
            'source' => 'backend_test',
        ],
    );

    return response()->json([
        'message' => 'Test notification sent.',
        'notification_id' => $notification->id,
    ]);
});
