<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminComplaintIndexRequest;
use App\Http\Requests\Admin\AdminComplaintUpdateRequest;
use App\Http\Resources\AdminComplaintResource;
use App\Models\Complaint;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

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
            ])
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where('status', $filters['status'])
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
        $data = $request->validated();
        $status = $data['status'];

        $complaint->status = $status;

        if (array_key_exists('resolution_notes', $data)) {
            $complaint->resolution_notes = $data['resolution_notes'];
        }

        $complaint->resolved_at = in_array(
            $status,
            ['resolved', 'rejected'],
            true
        )
            ? now()
            : null;

        $complaint->save();

        $complaint->load([
            'complainant:id,name,email',
            'reportedUser:id,name,email',
        ]);

        return $this->success(
            'تم تحديث الشكوى بنجاح. | Complaint updated successfully.',
            (new AdminComplaintResource($complaint))->resolve($request)
        );
    }
}
