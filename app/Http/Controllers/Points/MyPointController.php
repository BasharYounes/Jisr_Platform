<?php

namespace App\Http\Controllers\Points;

use App\Http\Controllers\Controller;
use App\Http\Resources\Points\PointTransactionResource;
use App\Services\Points\PointService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyPointController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PointService $pointService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $totalPoints = $this->pointService->getUserTotalPoints($request->user());

        return response()->json([
            'status' => true,
            'message' => 'User points retrieved successfully.',
            'data' => [
                'total_points' => $totalPoints,
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $transactions = $this->pointService->getUserPointTransactions(
            user: $request->user(),
            filters: $request->only('per_page')
        );

        return response()->json([
            'status' => true,
            'message' => 'User point history retrieved successfully.',
            'data' => PointTransactionResource::collection($transactions),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }
}
