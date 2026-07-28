<?php

namespace App\Http\Controllers\Supervisor;

use App\Domains\Supervisor\Actions\ChangeSupervisorAccessStatusAction;
use App\Domains\Supervisor\Requests\ChangeSupervisorAccessStatusRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SupervisorAccessController extends Controller
{
    public function block(
        ChangeSupervisorAccessStatusRequest $request,
        User $supervisor,
        ChangeSupervisorAccessStatusAction $action
    ): JsonResponse {
        $result = $action->block(
            lead: $request->user(),
            supervisor: $supervisor,
            reason: $request->validated('reason'),
        );

        return ApiResponse::success(
            'Supervisor blocked successfully',
            $result
        );
    }

    public function unblock(
        ChangeSupervisorAccessStatusRequest $request,
        User $supervisor,
        ChangeSupervisorAccessStatusAction $action
    ): JsonResponse {
        $result = $action->unblock(
            lead: $request->user(),
            supervisor: $supervisor,
            reason: $request->validated('reason'),
        );

        return ApiResponse::success(
            'Supervisor unblocked successfully',
            $result
        );
    }
}
