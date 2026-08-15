<?php

namespace App\Http\Controllers\Company;

use App\Enums\MentorApplicationSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mentor\CompanyMentorNominationIndexRequest;
use App\Http\Requests\Mentor\StoreCompanyMentorNominationRequest;
use App\Http\Resources\CompanyMentorNominationResource;
use App\Http\Resources\MentorApplicationResource;
use App\Models\Company;
use App\Models\MentorProfile;
use App\Services\Mentor\MentorApplicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CompanyMentorNominationController extends Controller
{
    public function __construct(
        private readonly MentorApplicationService $applicationService
    ) {}

    public function index(
        CompanyMentorNominationIndexRequest $request
    ): JsonResponse {
        $company = $this->companyFor($request->user()->id);
        $filters = $request->validated();

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $nominations = MentorProfile::query()
            ->where('company_id', $company->id)
            ->where(
                'source',
                MentorApplicationSource::CompanyNomination->value
            )
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where(
                    'status',
                    $filters['status']
                )
            )
            ->orderByDesc('id')
            ->paginate(
                $perPage,
                ['*'],
                'page',
                $page
            );

        return ApiResponse::success(
            'Mentor nominations retrieved successfully.',
            [
                'nominations' => CompanyMentorNominationResource::collection(
                    $nominations->getCollection()
                )->resolve($request),
                'pagination' => [
                    'current_page' => $nominations->currentPage(),
                    'last_page' => $nominations->lastPage(),
                    'per_page' => $nominations->perPage(),
                    'total' => $nominations->total(),
                ],
            ]
        );
    }

    public function store(
        StoreCompanyMentorNominationRequest $request
    ): JsonResponse {
        $company = $this->companyFor($request->user()->id);

        $data = $request->validated();
        unset($data['cv']);

        $application = $this->applicationService
            ->submitCompanyNomination(
                $request->user(),
                $company,
                $data,
                $request->file('cv')
            );

        return ApiResponse::success(
            'Mentor nomination submitted successfully.',
            new MentorApplicationResource($application),
            201
        );
    }

    private function companyFor(int $userId): Company
    {
        $company = Company::query()
            ->whereHas(
                'users',
                fn ($query) => $query->whereKey($userId)
            )
            ->first();

        if (! $company) {
            throw ValidationException::withMessages([
                'company' => [
                    'No company is linked to this account.',
                ],
            ]);
        }

        return $company;
    }
}
