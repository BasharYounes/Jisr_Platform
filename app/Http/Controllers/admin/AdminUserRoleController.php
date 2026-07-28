<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Admin\Actions\ManageUserRoleAction;
use App\Domains\Admin\Enums\ManagedUserRole;
use App\Domains\Admin\Enums\UserRoleOperation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManageUserRoleRequest;
use App\Models\User;
use App\Support\ApiResponse;
use BackedEnum;
use Illuminate\Http\JsonResponse;

class AdminUserRoleController extends Controller
{
    public function __invoke(
        ManageUserRoleRequest $request,
        User $user,
        ManageUserRoleAction $action
    ): JsonResponse {
        $validated = $request->validated();

        $updatedUser = $action->execute(
            admin: $request->user(),
            targetUser: $user,
            operation: UserRoleOperation::from(
                $validated['operation']
            ),
            role: ManagedUserRole::from(
                $validated['role']
            ),
            roleData: $validated['role_data'] ?? [],
        );

        $profile = $updatedUser->supervisorProfile;

        $specialization = $profile?->specialization;

        if ($specialization instanceof BackedEnum) {
            $specialization = $specialization->value;
        }

        return ApiResponse::success(
            'User role updated successfully',
            [
                'id' => $updatedUser->id,
                'name' => $updatedUser->name,
                'email' => $updatedUser->email,
                'is_active' => (bool) $updatedUser->is_active,

                'roles' => $updatedUser
                    ->getRoleNames()
                    ->sort()
                    ->values()
                    ->all(),

                'supervisor_profile' => $profile
                    ? [
                        'specialization' => $specialization,
                        'is_volunteer' => (bool) $profile->is_volunteer,
                    ]
                    : null,
            ]
        );
    }
}
