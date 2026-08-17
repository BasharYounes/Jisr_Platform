<?php

use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\NotificationController;
use App\Services\Notifications\NotificationService;
use App\Support\NotificationTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
});

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
