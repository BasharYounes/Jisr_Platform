<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminCompanyVerificationResource;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    use ApiResponse;

    protected AdminService $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    public function listUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => [
                'nullable',
                'string',
                Rule::in(['student', 'company', 'supervisor']),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $users = $this->adminService->listUsers(
            $validated['role'] ?? null,
            (int) ($validated['per_page'] ?? 20)
        );

        return $this->success(
            'Users retrieved successfully.',
            [
                'users' => AdminUserResource::collection(
                    $users->getCollection()
                )->resolve($request),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ]
        );
    }

    public function blockUser(User $user): JsonResponse
    {
        $blockedUser = $this->adminService->blockUser($user);

        return $this->success(
            'User blocked successfully.',
            new AdminUserResource($blockedUser)
        );
    }

    public function unblockUser(User $user): JsonResponse
    {
        $unblockedUser = $this->adminService->unblockUser($user);

        return $this->success(
            'User unblocked successfully.',
            new AdminUserResource($unblockedUser)
        );
    }

    public function getUnverifiedCompanies(): JsonResponse
    {
        $companies = $this->adminService->getUnverifiedCompanies();

        return $this->success(
            'Unverified companies retrieved successfully.',
            AdminCompanyVerificationResource::collection($companies)
                ->resolve(request())
        );
    }

    public function getCompanyDetails(int $companyId): JsonResponse
    {
        $company = $this->adminService->getCompanyDetails($companyId);

        return $this->success(
            'Company details retrieved successfully.',
            (new AdminCompanyVerificationResource($company))
                ->resolve(request())
        );
    }

    public function verifyCompany(int $id): JsonResponse
    {
        $result = $this->adminService->verifyCompany($id);

        if (! $result['status']) {
            return $this->error(
                $result['message'],
                [],
                400
            );
        }

        return $this->success(
            $result['message'],
            (new AdminCompanyVerificationResource($result['company']))
                ->resolve(request())
        );
    }

    public function rejectCompany(int $id): JsonResponse
    {
        $result = $this->adminService->rejectCompany($id);

        if (! $result['status']) {
            return $this->error(
                $result['message'],
                [],
                400
            );
        }

        return $this->success(
            $result['message'],
            (new AdminCompanyVerificationResource($result['company']))
                ->resolve(request())
        );
    }
}
