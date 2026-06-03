<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\Notifications\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payload = $this->notificationService->getUserNotifications($request->user());

        return ApiResponse::success('Notifications retrieved successfully.', [
            'notifications' => NotificationResource::collection($payload['notifications']),
            'meta' => [
                'unread_count' => $payload['unread_count'],
            ],
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $this->notificationService->markAsRead($request->user(), $notification);

        return ApiResponse::success('Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllAsRead($request->user());

        return ApiResponse::success('All notifications marked as read.');
    }
}
