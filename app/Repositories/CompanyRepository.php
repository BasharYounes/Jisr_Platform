<?php
namespace App\Repositories;

use App\Models\Company;
use App\Interfaces\CompanyRepositoryInterface;
use App\Models\User;
use Workbench\App\Models\User as ModelsUser;

class CompanyRepository implements CompanyRepositoryInterface
{
    public function create(array $data)
    {
        return Company::create($data);
    }

     public function findById(int $companyId):?Company
    {

        return Company::findOrFail($companyId);

    }

    public function getUnverifiedCompanies()
    {
        return User::where('is_verified_by_admin', 'pending')->get();
    }

     public function getCompanyByUserId(int $userId)
    {
        $user=User::findOrFail($userId);
        $company=Company::where('user_id', $userId)->first();
        return $company;
    }

    public function verify(Company $company): void
{
    $user = $company->user;

    if ($user) {
        $user->is_verified_by_admin = 'accepted';
        $user->save();
       }
    }
}
