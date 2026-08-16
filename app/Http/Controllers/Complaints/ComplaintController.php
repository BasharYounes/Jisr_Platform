<?php

namespace App\Http\Controllers\Complaints;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaints\StoreComplaintRequest;
use App\Http\Resources\ComplaintResource;
use App\Services\Complaints\ComplaintService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintService $complaintService,
    ) {}

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
