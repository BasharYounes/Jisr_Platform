<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSession;
use App\Services\Recommendations\LearningPathService;

class LearningController extends Controller
{
    public function __construct(
        private readonly LearningPathService $learningPathService
    ) {}

    public function path(AssessmentSession $session)
    {
        if ($session->UserID !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'message' => 'Learning path generated',
            'data' => $this->learningPathService->generate($session),
        ]);
    }
}
