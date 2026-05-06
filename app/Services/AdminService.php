<?php

namespace App\Services;

use App\Events\CompanyRejected;
use App\Events\CompanyVerified;
use App\Repositories\CompanyRepository;
use App\Models\Company;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Storage;



class AdminService
{

protected CompanyRepository $companyRepository;
protected UserRepository $userRepository;

    public function __construct(CompanyRepository $companyRepository, UserRepository $userRepository    )
    {
        $this->companyRepository = $companyRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * 
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnverifiedCompanies()
    {
        return $this->companyRepository->getUnverifiedCompanies();
    }

    public function listUsers()
    {
        return $this->userRepository->listUsers();
    }   
    public function getCompanyDetailsByUserId(int $companyId)
    {
        $company = $this->companyRepository->findById($companyId);
        $user = $company->load('users');

        if (!$company) {
            return null;
        }

        $pdfUrl = Storage::url($company->documentation_file); 

        return [
            'company' => $company, 
            'documentation_file' => $pdfUrl    
        ];
    }


    public function findById(int $id)
{
    $company = $this->companyRepository->findById($id);
    $company->load('users');
    return $company;
    }




      public function verifyCompany(int $companyId): array
{   
    $company = $this->companyRepository->findById($companyId);

    if (!$company) {
        return [
            'status' => false,
            'message' => 'Company not found'
        ];
    }

    $company->load('users');
    $user = $company->users->first();

    if (!$user) {
        return [
            'status' => false,
            'message' => 'Company has no associated user'
        ];
    }

   if ($user->is_verified_by_admin === 'accepted') {
    return [
        'status' => false,
        'message' => 'Company already verified'
    ];
}


    $user->is_verified_by_admin = 'accepted';
    $user->save();

     event(new CompanyVerified(
        company: $company,
        user: $user,
    ));

    return [
        'status' => true,
        'message' => 'Company verified successfully'
    ];
}

    public function rejectCompany(int $companyId): array
{   
    $company = $this->companyRepository->findById($companyId);

    if (!$company) {
        return [
            'status' => false,
            'message' => 'Company not found'
        ];
    }

    $company->load('users');
    $user = $company->users->first();

    if (!$user) {
        return [
            'status' => false,
            'message' => 'Company has no associated user'
        ];
    }

    if ($user->is_verified_by_admin === 'rejected') {
        return [
            'status' => false,
            'message' => 'Company already rejected'
        ];
    }

    $user->is_verified_by_admin = 'rejected';
    $user->save();


    event(new CompanyRejected(
        company: $company,
        user: $user,
    ));

    return [
        'status' => true,
        'message' => 'Company rejected successfully'
    ];
}

}
