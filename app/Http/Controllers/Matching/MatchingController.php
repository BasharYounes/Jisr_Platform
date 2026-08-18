<?php

namespace App\Http\Controllers\Matching;

use App\Domains\Matching\Handler\GetTopCandidatesForOpportunityHandler;
use App\Domains\Matching\Queries\GetTopCandidatesForOpportunity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

class MatchingController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly GetTopCandidatesForOpportunityHandler $handler
    ) {}

    /**
     * The route is currently declared outside the company route group in api.php.
     * Laravel 12 controller middleware keeps it protected without changing that route file.
     */
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'role:company',
        ];
    }

    public function topCandidates(Request $request, string $id): JsonResponse
    {
        abort_unless(ctype_digit($id) && (int) $id > 0, 404);

        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $companyId = (int) $request->user()
            ->companies()
            ->firstOrFail()
            ->id;

        $result = $this->handler->handle(
            new GetTopCandidatesForOpportunity(
                companyId: $companyId,
                opportunityId: (int) $id,
                limit: (int) ($validated['limit'] ?? 20)
            )
        );

        return response()->json([
            'success' => true,
            'message' => 'تم ترتيب المتقدمين للفرصة بنجاح. | Opportunity applicants ranked successfully.',
            'data' => $result,
            'meta' => [
                'candidate_count' => $result->count(),
                'weights' => GetTopCandidatesForOpportunityHandler::weights(),
                'score_scale' => '0-100',
            ],
        ]);
    }
}
