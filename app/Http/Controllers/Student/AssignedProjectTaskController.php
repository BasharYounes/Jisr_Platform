<?php

namespace App\Http\Controllers\Student;

use App\Domains\Student\Actions\ListAssignedProjectTasksAction;
use App\Domains\Student\Requests\ListAssignedProjectTasksRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\Student\AssignedProjectTaskResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AssignedProjectTaskController extends Controller
{
    public function index(
        ListAssignedProjectTasksRequest $request,
        ListAssignedProjectTasksAction $action
    ): JsonResponse {
        $tasks = $action->execute(
            student: $request->user(),
            filters: $request->validated(),
        );

        return ApiResponse::success(
            'Assigned project tasks retrieved successfully',
            [
                'tasks' => AssignedProjectTaskResource::collection(
                    $tasks->getCollection()
                )->resolve($request),
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                ],
            ]
        );
    }
}
