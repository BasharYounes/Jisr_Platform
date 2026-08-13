<?php

namespace App\Http\Controllers\admin;

use App\Enums\SupervisorSpecialization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCreateSupervisorRequest;
use App\Services\Auth\Strategies\SupervisorRegisterStrategy;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminSupervisorController extends Controller
{
    use ApiResponse;

    public function store(
        AdminCreateSupervisorRequest $request,
        SupervisorRegisterStrategy $registerStrategy
    ): JsonResponse {
        $result = $registerStrategy->register($request->validated());

        $user = $result['user']->load('roles');
        $profile = $result['supervisor_profile'];

        $specialization = $profile->specialization;

        return $this->success(
            'تم إنشاء حساب المشرف بنجاح. | Supervisor account created successfully.',
            [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->values()->all(),
                'supervisor_profile' => [
                    'specialization' => $specialization instanceof SupervisorSpecialization
                        ? $specialization->value
                        : (string) $specialization,
                    'is_volunteer' => (bool) $profile->is_volunteer,
                ],
            ],
            201
        );
    }
}
