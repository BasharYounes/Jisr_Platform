<?php

namespace App\Services;

use App\Events\CompanyRejected;
use App\Events\CompanyVerified;
use App\Models\Company;
use App\Models\User;
use App\Repositories\CompanyRepository;
use App\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(
        protected CompanyRepository $companyRepository,
        protected UserRepository $userRepository
    ) {}

    public function getUnverifiedCompanies(): Collection
    {
        return $this->companyRepository->getUnverifiedCompanies();
    }

    public function listUsers(
        ?string $role = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->userRepository->listUsers($role, $perPage);
    }

    public function blockUser(User $user): User
    {
        $this->ensureAccessStatusCanBeManaged($user);

        return DB::transaction(function () use ($user): User {
            $user->update([
                'is_active' => false,
            ]);

            $user->tokens()->delete();

            return $user->refresh()->load('roles');
        });
    }

    public function unblockUser(User $user): User
    {
        $this->ensureAccessStatusCanBeManaged($user);

        $user->update([
            'is_active' => true,
        ]);

        return $user->refresh()->load('roles');
    }

    public function getCompanyDetails(int $companyId): Company
    {
        return $this->companyRepository->findById($companyId);
    }

    public function findById(int $id): Company
    {
        return $this->companyRepository->findById($id);
    }

    public function verifyCompany(int $companyId): array
    {
        $company = $this->companyRepository->findById($companyId);
        $owner = $this->companyOwner($company);

        if (! $owner) {
            return [
                'status' => false,
                'message' => 'Company has no associated owner user',
            ];
        }

        $updatedOwner = DB::transaction(function () use ($owner): ?User {
            $lockedOwner = User::query()
                ->whereKey($owner->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOwner->is_verified_by_admin !== 'pending') {
                return null;
            }

            $lockedOwner->is_verified_by_admin = 'accepted';
            $lockedOwner->save();

            return $lockedOwner;
        });

        if (! $updatedOwner) {
            return [
                'status' => false,
                'message' => 'Company verification can only be accepted while pending',
            ];
        }

        $company = $this->companyRepository->findById($companyId);

        event(new CompanyVerified(
            company: $company,
            user: $updatedOwner,
        ));

        return [
            'status' => true,
            'message' => 'Company verified successfully',
            'company' => $company,
        ];
    }

    public function rejectCompany(int $companyId): array
    {
        $company = $this->companyRepository->findById($companyId);
        $owner = $this->companyOwner($company);

        if (! $owner) {
            return [
                'status' => false,
                'message' => 'Company has no associated owner user',
            ];
        }

        $updatedOwner = DB::transaction(function () use ($owner): ?User {
            $lockedOwner = User::query()
                ->whereKey($owner->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOwner->is_verified_by_admin !== 'pending') {
                return null;
            }

            $lockedOwner->is_verified_by_admin = 'rejected';
            $lockedOwner->save();

            $lockedOwner->tokens()->delete();

            return $lockedOwner;
        });

        if (! $updatedOwner) {
            return [
                'status' => false,
                'message' => 'Company verification can only be rejected while pending',
            ];
        }

        $company = $this->companyRepository->findById($companyId);

        event(new CompanyRejected(
            company: $company,
            user: $updatedOwner,
        ));

        return [
            'status' => true,
            'message' => 'Company rejected successfully',
            'company' => $company,
        ];
    }

    private function companyOwner(Company $company): ?User
    {
        return $company->users->firstWhere('pivot.role', 'owner')
            ?? $company->users->first();
    }

    private function ensureAccessStatusCanBeManaged(User $user): void
    {
        if ($user->hasRole('admin')) {
            throw new AuthorizationException(
                'Admin accounts cannot be blocked or unblocked through this endpoint.'
            );
        }
    }
}