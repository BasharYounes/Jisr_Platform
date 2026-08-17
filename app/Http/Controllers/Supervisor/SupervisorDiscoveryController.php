<?php

namespace App\Http\Controllers\Supervisor;

use App\Domains\Supervisor\Actions\ListLeadProjectAssignmentsAction;
use App\Domains\Supervisor\Actions\ListLeadProjectEvaluationsAction;
use App\Domains\Supervisor\Actions\ListMyProjectEvaluationsAction;
use App\Domains\Supervisor\Requests\ListLeadProjectAssignmentsRequest;
use App\Domains\Supervisor\Requests\ListLeadProjectEvaluationsRequest;
use App\Domains\Supervisor\Requests\ListMyProjectEvaluationsRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\Supervisor\LeadProjectAssignmentListResource;
use App\Http\Resources\Supervisor\LeadProjectEvaluationListResource;
use App\Http\Resources\Supervisor\MyProjectEvaluationListResource;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class SupervisorDiscoveryController extends Controller
{
    public function leadEvaluations(
        ListLeadProjectEvaluationsRequest $request,
        ListLeadProjectEvaluationsAction $action
    ): JsonResponse {
        $paginator = $action->execute(
            supervisorLead: $request->user(),
            filters: $request->validated(),
        );

        return ApiResponse::success(
            'Project evaluations retrieved successfully',
            [
                'evaluations' => LeadProjectEvaluationListResource::collection(
                    $paginator->getCollection()
                )->resolve($request),

                'pagination' => $this->pagination($paginator),
            ]
        );
    }

    public function myEvaluations(
        ListMyProjectEvaluationsRequest $request,
        ListMyProjectEvaluationsAction $action
    ): JsonResponse {
        $paginator = $action->execute(
            supervisor: $request->user(),
            filters: $request->validated(),
        );

        return ApiResponse::success(
            'My project evaluations retrieved successfully',
            [
                'evaluations' => MyProjectEvaluationListResource::collection(
                    $paginator->getCollection()
                )->resolve($request),

                'pagination' => $this->pagination($paginator),
            ]
        );
    }

    public function leadAssignments(
        ListLeadProjectAssignmentsRequest $request,
        ListLeadProjectAssignmentsAction $action
    ): JsonResponse {
        $paginator = $action->execute(
            supervisorLead: $request->user(),
            filters: $request->validated(),
        );

        return ApiResponse::success(
            'Lead project assignments retrieved successfully',
            [
                'assignments' => LeadProjectAssignmentListResource::collection(
                    $paginator->getCollection()
                )->resolve($request),

                'pagination' => $this->pagination($paginator),
            ]
        );
    }

    private function pagination(
        LengthAwarePaginator $paginator
    ): array {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
