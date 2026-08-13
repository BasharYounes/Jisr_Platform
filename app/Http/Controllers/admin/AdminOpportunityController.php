<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminOpportunityIndexRequest;
use App\Http\Resources\Opportunities\OpportunityResource;
use App\Models\Opportunity;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminOpportunityController extends Controller
{
    use ApiResponse;

    public function index(AdminOpportunityIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $opportunities = Opportunity::query()
            ->select([
                'id',
                'company_id',
                'title',
                'description',
                'type',
                'location',
                'salary_min',
                'salary_max',
                'status',
                'deadline',
                'posted_at',
                'created_at',
                'updated_at',
            ])
            ->with('company')
            ->withCount('applications')
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                isset($filters['type']),
                fn ($query) => $query->where('type', $filters['type'])
            )
            ->when(
                isset($filters['search']),
                function ($query) use ($filters): void {
                    $search = $filters['search'];

                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('title', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%')
                            ->orWhere('location', 'like', '%'.$search.'%');
                    });
                }
            )
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success(
            'تم جلب فرص المنصة بنجاح. | Platform opportunities retrieved successfully.',
            [
                'opportunities' => OpportunityResource::collection(
                    $opportunities->getCollection()
                )->resolve($request),
                'pagination' => [
                    'current_page' => $opportunities->currentPage(),
                    'last_page' => $opportunities->lastPage(),
                    'per_page' => $opportunities->perPage(),
                    'total' => $opportunities->total(),
                ],
            ]
        );
    }
}
