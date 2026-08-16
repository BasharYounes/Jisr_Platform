<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mentor\StoreSelfMentorApplicationRequest;
use App\Http\Resources\MentorApplicationResource;
use App\Models\MentorProfile;
use App\Services\Mentor\MentorApplicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorApplicationController extends Controller
{
    public function __construct(
        private readonly MentorApplicationService $applicationService
    ) {}

    public function store(
        StoreSelfMentorApplicationRequest $request
    ): JsonResponse {
        $data = $request->validated();
        unset($data['cv']);

        $application = $this->applicationService
            ->submitSelfApplication(
                $request->user(),
                $data,
                $request->file('cv')
            );

        return ApiResponse::success(
            'Mentor application submitted successfully.',
            new MentorApplicationResource($application),
            201
        );
    }

    public function showMine(Request $request): JsonResponse
    {
        if (! $request->user()->hasAnyRole([
            'student',
            'supervisor',
            'supervisor_lead',
        ])) {
            abort(403);
        }

        $application = MentorProfile::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $application) {
            return ApiResponse::error(
                'No mentor application found.',
                404
            );
        }

        return ApiResponse::success(
            'Mentor application retrieved successfully.',
            new MentorApplicationResource($application)
        );
    }
}
