<?php

namespace App\Http\Controllers;

use App\Models\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Save the current Android device FCM token for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        /*
         * The token is globally unique in Firebase.
         * If the same phone logs into another account, ownership moves
         * to the currently authenticated student.
         */
        $deviceToken = UserDeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => 'android',
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device token saved successfully.',
            'data' => [
                'id' => $deviceToken->id,
                'platform' => $deviceToken->platform,
                'last_seen_at' => $deviceToken->last_seen_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Remove the current Android device token when the user logs out.
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $deleted = UserDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device token removed successfully.',
            'data' => [
                'deleted' => (bool) $deleted,
            ],
        ]);
    }
}
