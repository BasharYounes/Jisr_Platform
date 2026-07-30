<?php

namespace App\Http\Controllers\Supervisor;

use App\Domains\Supervisor\Actions\ListProjectEvaluationAppealsAction;
use App\Domains\Supervisor\Actions\ReviewProjectEvaluationAppealAction;
use App\Domains\Supervisor\Enums\ProjectEvaluationAppealDecision;
use App\Domains\Supervisor\Enums\ProjectEvaluationAppealStatus;
use App\Domains\Supervisor\Requests\ListProjectEvaluationAppealsRequest;
use App\Domains\Supervisor\Requests\ReviewProjectEvaluationAppealRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\Supervisor\EvaluationRevisionRequestResource;
use App\Http\Resources\Supervisor\ProjectEvaluationAppealResource;
use App\Http\Resources\Supervisor\ProjectEvaluationAppealSummaryResource;
use App\Models\ProjectEvaluationAppeal;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectEvaluationAppealReviewController extends Controller
{
    public function index(
        ListProjectEvaluationAppealsRequest $request,
        ListProjectEvaluationAppealsAction $action
    ): JsonResponse {
        Gate::authorize(
            'viewAny',
            ProjectEvaluationAppeal::class
        );

        $validated = $request->validated();

        $status = isset($validated['status'])
            ? ProjectEvaluationAppealStatus::from(
                $validated['status']
            )
            : null;

        $appeals = $action->execute(
            lead: $request->user(),
            status: $status,
            perPage: (int) (
                $validated['per_page'] ?? 15
            ),
        );

        return ApiResponse::success(
            'Project evaluation appeals retrieved successfully',
            [
                'appeals' => ProjectEvaluationAppealSummaryResource::collection(
                    $appeals->getCollection()
                )->resolve($request),

                'pagination' => [
                'current_page' => $appeals->currentPage(),

                'last_page' => $appeals->lastPage(),

                'per_page' => $appeals->perPage(),

                'total' => $appeals->total(),
                ],
            ]
        );
    }

    public function show(
        Request $request,
        ProjectEvaluationAppeal $projectEvaluationAppeal
    ): JsonResponse {
        Gate::authorize(
            'view',
            $projectEvaluationAppeal
        );

        $projectEvaluationAppeal->load([
            'student:id,name,email',
            'reviewedBy:id,name,email',

            'evaluation.assignment.projectTemplate',
            'evaluation.student',
            'evaluation.supervisor',
            'evaluation.items.criteria',
            'evaluation.items.evidences',
        ]);

        return ApiResponse::success(
            'Project evaluation appeal retrieved successfully',
            new ProjectEvaluationAppealResource(
                $projectEvaluationAppeal
            )
        );
    }

    public function review(
        ReviewProjectEvaluationAppealRequest $request,
        ProjectEvaluationAppeal $projectEvaluationAppeal,
        ReviewProjectEvaluationAppealAction $action
    ): JsonResponse {
        Gate::authorize(
            'review',
            $projectEvaluationAppeal
        );

        $validated = $request->validated();

        $result = $action->execute(
            appeal: $projectEvaluationAppeal,
            reviewedBy: $request->user(),
            decision: ProjectEvaluationAppealDecision::from(
                $validated['decision']
            ),
            reviewNotes: $validated['review_notes'],
        );

        return ApiResponse::success(
            'Project evaluation appeal reviewed successfully',
            [
                'appeal' => (new ProjectEvaluationAppealResource(
                    $result['appeal']
                ))->resolve($request),

                'revision_request' => $result['revision_request']
                        ? (new EvaluationRevisionRequestResource(
                            $result['revision_request']
                        ))->resolve($request)
                        : null,
            ]
        );
    }
}
