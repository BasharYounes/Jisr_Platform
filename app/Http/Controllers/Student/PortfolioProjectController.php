<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\PortfolioProjects\StorePortfolioProjectRequest;
use App\Http\Requests\PortfolioProjects\UpdatePortfolioProjectRequest;
use App\Http\Requests\User\StorePortfolioProjectRequest as UserStorePortfolioProjectRequest;
use App\Http\Requests\User\UpdatePortfolioProjectRequest as UserUpdatePortfolioProjectRequest;
use App\Http\Resources\PortfolioProjectResource;
use App\Services\PortfolioProjects\PortfolioProjectService;
use App\Services\User\PortfolioProjectService as UserPortfolioProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class StudentPortfolioProjectController extends Controller
{
    public function __construct(
        private readonly UserPortfolioProjectService $portfolioProjectService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $projects = $this->portfolioProjectService->getStudentProjects(
            userId: Auth::id()
        );

        return PortfolioProjectResource::collection($projects);
    }

    public function store(UserStorePortfolioProjectRequest $request): JsonResponse
    {
        $project = $this->portfolioProjectService->createManualProject(
            userId: Auth::id(),
            data: $request->validated()
        );

        return response()->json([
            'message' => 'تمت إضافة المشروع إلى البورتفوليو بنجاح. | Portfolio project added successfully.',
            'data' => new PortfolioProjectResource($project),
        ], 201);
    }

    public function show(int $portfolioProjectId): JsonResponse
    {
        $project = $this->portfolioProjectService->getStudentProject(
            userId: Auth::id(),
            projectId: $portfolioProjectId
        );

        return response()->json([
            'data' => new PortfolioProjectResource($project),
        ]);
    }

    public function update(UserUpdatePortfolioProjectRequest $request, int $portfolioProjectId): JsonResponse
    {
        $project = $this->portfolioProjectService->updateStudentProject(
            userId: Auth::id(),
            projectId: $portfolioProjectId,
            data: $request->validated()
        );

        return response()->json([
            'message' => 'تم تعديل مشروع البورتفوليو بنجاح. | Portfolio project updated successfully.',
            'data' => new PortfolioProjectResource($project),
        ]);
    }

    public function destroy(int $portfolioProjectId): JsonResponse
    {
        $this->portfolioProjectService->deleteStudentProject(
            userId: Auth::id(),
            projectId: $portfolioProjectId
        );

        return response()->json([
            'message' => 'تم حذف مشروع البورتفوليو بنجاح. | Portfolio project deleted successfully.',
        ]);
    }
}