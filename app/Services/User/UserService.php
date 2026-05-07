<?php

namespace App\Services\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserService
{

  public function editCompanyProfile($user, $company, array $data, $request)
{
    if (!$company) {
        throw ValidationException::withMessages([
            'company' => ['Company profile not found.'],
        ]);
    }

    $userData = collect($data)->only([
        'name',
        'bio',
        'email',
    ])->toArray();

    $companyData = collect($data)->only([
        'industry',
        'location',
        'website',
    ])->toArray();

    if ($request->hasFile('profile_picture_url')) {
        $path = $request->file('profile_picture_url')
            ->store('profiles', 'public');

        $userData['profile_picture_url'] = $path;
    }

    if ($request->hasFile('documentation_file')) {
        $path = $request->file('documentation_file')
            ->store('companies/documentations', 'public');

        $companyData['documentation_file'] = $path;
    }

    if (!empty($userData)) {
        $user->update($userData);
    }

    if (!empty($companyData)) {
        $company->update($companyData);
    }

    return $company->fresh();
}

    
}
