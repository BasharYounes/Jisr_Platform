<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Success Response
     */
    public function success(
        string $message = 'Success',
        mixed $data = null,
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Error Response
     */
    public function error(
        string $message = 'Something went wrong',
        mixed $errors = null,
        int $statusCode = 400
    ): JsonResponse {
        return response()->json([
            'status' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    /**
     * Token Response (Auth)
     */
    public function token(
        string $token,
        mixed $user,
        string $message = 'Authenticated successfully'
    ): JsonResponse {
        return $this->success($message, [
            'user' => $user,
            'token' => $token,
            'type' => 'Bearer',
        ]);
    }
}