<?php

namespace App\Http\Controllers\admin;

use App\Exceptions\AIProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminMentorApplicationIndexRequest;
use App\Http\Requests\Admin\RejectMentorApplicationRequest;
use App\Http\Resources\AdminMentorApplicationResource;
use App\Models\MentorProfile;
use App\Services\Mentor\MentorApplicationReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminMentorApplicationController extends Controller
{
    public function __construct(
        private readonly MentorApplicationReviewService $reviewService
    ) {}

    public function index(
        AdminMentorApplicationIndexRequest $request
    ): JsonResponse {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $applications = MentorProfile::query()
            ->with([
                'submittedBy:id,name,email',
                'company:id,industry,website',
            ])
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where(
                    'status',
                    $filters['status']
                )
            )
            ->when(
                isset($filters['source']),
                fn ($query) => $query->where(
                    'source',
                    $filters['source']
                )
            )
            ->when(
                isset($filters['specialization']),
                fn ($query) => $query->where(
                    'specialization',
                    $filters['specialization']
                )
            )
            ->when(
                filled($filters['search'] ?? null),
                function ($query) use ($filters): void {
                    $search = trim($filters['search']);

                    $query->where(function ($nested) use ($search): void {
                        $nested
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                }
            )
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::success(
            'Mentor applications retrieved successfully.',
            [
                'applications' => AdminMentorApplicationResource::collection(
                    $applications->getCollection()
                )->resolve($request),
                'pagination' => [
                    'current_page' => $applications->currentPage(),
                    'last_page' => $applications->lastPage(),
                    'per_page' => $applications->perPage(),
                    'total' => $applications->total(),
                ],
            ]
        );
    }

    public function show(
        Request $request,
        MentorProfile $mentorProfile
    ): JsonResponse {
        $mentorProfile->load([
            'submittedBy:id,name,email',
            'company:id,industry,website',
            'skills:id,name,category',
            'reviewedBy:id,name,email',
        ]);

        return ApiResponse::success(
            'Mentor application retrieved successfully.',
            (new AdminMentorApplicationResource(
                $mentorProfile
            ))->resolve($request)
        );
    }

    public function downloadCv(
        MentorProfile $mentorProfile
    ): BinaryFileResponse|JsonResponse {
        if (blank($mentorProfile->cv_path)) {
            return ApiResponse::error(
                'This mentor application does not have a CV.',
                404
            );
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($mentorProfile->cv_path)) {
            return ApiResponse::error(
                'Mentor CV file not found.',
                404
            );
        }

        $extension = pathinfo(
            $mentorProfile->cv_path,
            PATHINFO_EXTENSION
        );

        $downloadName = 'mentor-application-'
            .$mentorProfile->id
            .($extension !== '' ? ".{$extension}" : '');

        return response()->download(
            $disk->path($mentorProfile->cv_path),
            $downloadName
        );
    }

    public function approve(
        Request $request,
        MentorProfile $mentorProfile
    ): JsonResponse {
        try {
            $application = $this->reviewService->approve(
                $mentorProfile,
                $request->user()
            );
        } catch (AIProviderException $exception) {
            report($exception);

            return ApiResponse::error(
                'Mentor skill extraction failed. '
                .'The application remains pending.',
                502
            );
        }

        $application->load([
            'submittedBy:id,name,email',
            'company:id,industry,website',
            'skills:id,name,category',
            'reviewedBy:id,name,email',
        ]);

        return ApiResponse::success(
            'Mentor application approved successfully.',
            (new AdminMentorApplicationResource(
                $application
            ))->resolve($request)
        );
    }

    public function reject(
        RejectMentorApplicationRequest $request,
        MentorProfile $mentorProfile
    ): JsonResponse {
        $application = $this->reviewService->reject(
            $mentorProfile,
            $request->user(),
            $request->validated('reason')
        );

        $application->load([
            'submittedBy:id,name,email',
            'company:id,industry,website',
            'skills:id,name,category',
            'reviewedBy:id,name,email',
        ]);

        return ApiResponse::success(
            'Mentor application rejected successfully.',
            (new AdminMentorApplicationResource(
                $application
            ))->resolve($request)
        );
    }
}
