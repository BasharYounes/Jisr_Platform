<?php

namespace App\Http\Controllers\Student;

use App\Enums\MentorApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mentor\StudentMentorIndexRequest;
use App\Http\Resources\StudentMentorResource;
use App\Models\MentorProfile;
use App\Services\Mentor\StudentMentorDiscoveryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentMentorController extends Controller
{
    public function __construct(
        private readonly StudentMentorDiscoveryService $discoveryService
    ) {}

    public function index(
        StudentMentorIndexRequest $request
    ): JsonResponse {
        $result = $this->discoveryService->discover(
            $request->user(),
            $request->validated()
        );

        $paginator = $result['paginator'];

        return ApiResponse::success(
            'Approved mentors retrieved successfully.',
            [
                'recommendation_context' => $result['context'],
                'mentors' => StudentMentorResource::collection(
                    $paginator->getCollection()
                )->resolve($request),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]
        );
    }

    public function show(
        Request $request,
        MentorProfile $mentorProfile
    ): JsonResponse {
        if (! $request->user()->hasRole('student')) {
            abort(403);
        }

        if (
            $mentorProfile->status
            !== MentorApplicationStatus::Approved
        ) {
            abort(404);
        }

        $mentorProfile->load([
            'skills:id,name,category',
            'company:id,industry,website',
        ]);

        $mentorProfile = $this->discoveryService->enrichSingle(
            $request->user(),
            $mentorProfile
        );

        return ApiResponse::success(
            'Mentor retrieved successfully.',
            (new StudentMentorResource(
                $mentorProfile
            ))->resolve($request)
        );
    }
}
