<?php

namespace App\Http\Controllers;

use App\Http\Resources\Company\CompanyHomeResource;
use App\Services\Company\CompanyHomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyHomeController extends Controller
{
    public function __construct(
        private readonly CompanyHomeService $companyHomeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $company = $user->companies()->firstOrFail();

        $homeData = $this->companyHomeService->getHomeData($company->id);

        return response()->json([
            'message' => 'Company home data retrieved successfully.',
            'data' => new CompanyHomeResource($homeData),
        ]);
    }
}
