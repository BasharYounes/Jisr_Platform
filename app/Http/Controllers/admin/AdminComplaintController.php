<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminComplaintIndexRequest;
use App\Http\Requests\Admin\AdminComplaintUpdateRequest;
use App\Http\Resources\AdminComplaintResource;
use App\Models\Complaint;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AdminComplaintController extends Controller
{
    use ApiResponse;

    public function index(AdminComplaintIndexRequest $request): JsonResponse
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
            ->with([
                'complainant:id,name,email',
                'reportedUser:id,name,email',
                'reportedMentorProfile:id,user_id,full_name,email,specialization,professional_title',
            ])
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                isset($filters['context_type']),
                fn ($query) => $query->where(
                    'context_type',
                    $filters['context_type']
                )
            )
            ->when(
                ($filters['target_type'] ?? null) === 'user',
                fn ($query) => $query->whereNotNull('reported_user_id')
            )
            ->when(
                ($filters['target_type'] ?? null) === 'mentor',
                fn ($query) => $query->whereNotNull(
                    'reported_mentor_profile_id'
                )
            )
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success(
            'تم جلب الشكاوى بنجاح. | Complaints retrieved successfully.',
            [
                'complaints' => AdminComplaintResource::collection(
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

    public function update(
        AdminComplaintUpdateRequest $request,
        Complaint $complaint
    ): JsonResponse {
        if ($complaint->isClosed()) {
            throw ValidationException::withMessages([
                'status' => [
                    'لا يمكن تعديل شكوى تم حلها أو رفضها مسبقاً. | A resolved or rejected complaint cannot be changed.',
                ],
            ]);
        }

        $data = $request->validated();
        $status = $data['status'];

        $complaint->status = $status;

        if (array_key_exists('resolution_notes', $data)) {
            $complaint->resolution_notes = $data['resolution_notes'];
        }

        $isClosing = in_array(
            $status,
            ['resolved', 'rejected'],
            true
        );

        $complaint->resolved_at = $isClosing
            ? now()
            : null;

        if ($isClosing) {
            $complaint->deduplication_key = null;
        }

        $complaint->save();

        $complaint->load([
            'complainant:id,name,email',
            'reportedUser:id,name,email',
            'reportedMentorProfile:id,user_id,full_name,email,specialization,professional_title',
        ]);

        return $this->success(
            'تم تحديث الشكوى بنجاح. | Complaint updated successfully.',
            (new AdminComplaintResource($complaint))->resolve($request)
        );
    }
}
