<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Http\Requests\CompanyProfileRequest;
use Illuminate\Support\Facades\Auth;
use App\Services\User\UserService;
use App\Traits\ApiResponse;


class UserController extends Controller 
{
    use ApiResponse;    
    protected $UserService;

    public function __construct(UserService $UserService)
    {
        $this->UserService = $UserService; 
    }


   // Company
    public function getProfileCompany()
    {
        $company = Auth::user()->companies()->first();

        if (!$company) {
            return $this->error('Company profile not found', 404);
        }

        return new CompanyResource($company);
    }
    
   public function editProfile(CompanyProfileRequest $request)
    {
        $user = Auth::user();
      $company = Auth::user()->companies()->first();

    $company = $this->UserService->editCompanyProfile(
        $user,
        $company,
        $request->validated(),
        $request
    );
        return new CompanyResource($company);
    }

    //Student
    // public function getPofileStudent (){
    //     $user=Auth::user()->studentProfile;
    //     return new UserResource($user);
    // }
}
