<?php

namespace App\Http\Controllers\Supervisor;

use App\Domains\Supervisor\Actions\ListSupervisorsBySpecializationAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Supervisor\SupervisorSummaryResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorLeadController extends Controller
{
    public function index(
        Request $request,
        ListSupervisorsBySpecializationAction $action
    ): JsonResponse {
        $supervisors = $action->execute($request->user());

        return ApiResponse::success(
            'Supervisors retrieved successfully',
            SupervisorSummaryResource::collection($supervisors)
        );
    }
}
