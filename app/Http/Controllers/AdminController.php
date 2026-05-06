<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyResource;
use App\Services\AdminService;
use App\Traits\ApiResponse;

class AdminController extends Controller
{
    use ApiResponse;

    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService; 
    }

    public function listUsers()
    {
        $users = $this->adminService->listUsers();

        return $this->success(
            'Users retrieved successfully.',
            $users
        );
    }

    public function getUnverifiedCompanies()
    {
        $companies = $this->adminService->getUnverifiedCompanies();

        return $this->success(
            'Unverified companies retrieved successfully.',
            $companies
        );
    }

    public function getCompanyDetails($companyId)
    {
        $companyDetails = $this->adminService->getCompanyDetailsByUserId($companyId);

        if (!$companyDetails) {
            return $this->error('Company not found', [], 404);
        }

        return $this->success(
            'Company details retrieved successfully.',
            [
                'company' => $companyDetails['company'],
                'documentation_file' => $companyDetails['documentation_file']
            ]
        );
    }

    public function verifyCompany(int $id)
    {
        $result = $this->adminService->verifyCompany($id);

        if (!$result['status']) {
            return $this->error(
                $result['message'],
                [],
                400
            );
        }
        $company = $this->adminService->findById($id);

        return $this->success(
            $result['message'],
            new CompanyResource($company),
            200
        );
    }


     public function rejectCompany(int $id)
    {
        $result = $this->adminService->rejectCompany($id);

        if (!$result['status']) {
            return $this->error(
                $result['message'],
                [],
                400
            );
        }
        $company = $this->adminService->findById($id);

        return $this->success(
            $result['message'],
            new CompanyResource($company),
            200
        );
    }
}