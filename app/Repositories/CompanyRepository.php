<?php

namespace App\Repositories;

use App\Interfaces\CompanyRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class CompanyRepository implements CompanyRepositoryInterface
{
    public function create(array $data)
    {
        return Company::create($data);
    }

    public function findById(int $companyId): Company
    {
        return Company::query()
            ->with([
                'users' => fn ($query) => $query
                    ->where('company_users.role', 'owner'),
            ])
            ->findOrFail($companyId);
    }

    public function getUnverifiedCompanies(): Collection
    {
        return Company::query()
            ->whereHas('users', function ($query): void {
                $query
                    ->where('company_users.role', 'owner')
                    ->where('users.is_verified_by_admin', 'pending');
            })
            ->with([
                'users' => fn ($query) => $query
                    ->where('company_users.role', 'owner'),
            ])
            ->orderByDesc('companies.id')
            ->get();
    }

    public function getCompanyByUserId(int $userId): ?Company
    {
        return Company::query()
            ->whereHas('users', fn ($query) => $query->whereKey($userId))
            ->first();
    }

    public function verify(Company $company): void
    {
        $company->load([
            'users' => fn ($query) => $query
                ->where('company_users.role', 'owner'),
        ]);

        $owner = $company->users->first();

        if (
            $owner instanceof User
            && $owner->is_verified_by_admin === 'pending'
        ) {
            $owner->update([
                'is_verified_by_admin' => 'accepted',
            ]);
        }
    }
}
