<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyStudents\SearchCompanyStudentsRequest;
use App\Http\Resources\CompanyStudents\CompanyStudentCollection;
use App\Http\Resources\CompanyStudents\CompanyStudentDetailsResource;
use App\Services\CompanyStudents\CompanyStudentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CompanyStudentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CompanyStudentService $companyStudentService
    ) {}

    public function index(
        SearchCompanyStudentsRequest $request
    ): JsonResponse {
        $students = $this->companyStudentService->search(
            $request->validated()
        );

        return $this->success(
            message: 'تم جلب الطلاب بنجاح. | Students retrieved successfully.',

            data: new CompanyStudentCollection($students)
        );
    }

    public function show(int $studentId): JsonResponse
    {
        $student = $this->companyStudentService->getDetails(
            $studentId
        );

        return $this->success(
            message: 'تم جلب تفاصيل الطالب بنجاح. | Student details retrieved successfully.',

            data: new CompanyStudentDetailsResource($student)
        );
    }
}
