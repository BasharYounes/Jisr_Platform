<?php

namespace App\Http\Controllers\Complaints;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaints\MyComplaintIndexRequest;
use App\Http\Requests\Complaints\StoreComplaintRequest;
use App\Http\Resources\ComplaintResource;
use App\Http\Resources\MyComplaintResource;
use App\Models\Complaint;
use App\Services\Complaints\ComplaintService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintService $complaintService,
    ) {}

    public function mine(MyComplaintIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $complaints = Complaint::query()
            ->select([
                'id',
                'complainant_user_id',
                'reported_user_id',
                'reported_mentor_profile_id',
                'context_type',
                'context_id',
                'reason',
                'status',
                'resolved_at',
                'resolution_notes',
                'created_at',
                'updated_at',
            ])
            ->where('complainant_user_id', $request->user()->id)
            ->with([
                'reportedUser:id,name,email',
                'reportedMentorProfile:id,full_name,email,specialization,professional_title',
            ])
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where(
                    'status',
                    $filters['status']
                )
            )
            ->when(
                isset($filters['context_type']),
                fn ($query) => $query->where(
                    'context_type',
                    $filters['context_type']
                )
            )
            ->orderByDesc('id')
            ->paginate(
                $perPage,
                ['*'],
                'page',
                $page
            );

        return ApiResponse::success(
            'تم جلب شكاواك بنجاح. | Your complaints retrieved successfully.',
            [
                'complaints' => MyComplaintResource::collection(
                    $complaints->getCollection()
                )->resolve($request),
                'pagination' => [
                    'current_page' => $complaints->currentPage(),
                    'last_page' => $complaints->lastPage(),
                    'per_page' => $complaints->perPage(),
                    'total' => $complaints->total(),
                ],
            ]
        );
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $complaint = $this->complaintService->create(
            complainant: $request->user(),
            data: $request->validated(),
        );

        return ApiResponse::success(
            'تم إرسال الشكوى بنجاح. | Complaint submitted successfully.',
            (new ComplaintResource($complaint))->resolve($request),
            201,
        );
    }
}
