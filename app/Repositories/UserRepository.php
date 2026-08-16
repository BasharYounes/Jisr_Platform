<?php

namespace App\Repositories;

use App\Interfaces\UserRepositoryInterface;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $data)
    {
        return User::create($data);
    }

    public function findByEmailOrFail(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function listUsers(
        ?string $role = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = User::query()
            ->select([
                'id',
                'name',
                'email',
                'is_active',
                'is_verified_by_admin',
                'profile_picture_url',
                'created_at',
            ])
            ->with('roles:id,name')
            ->orderBy('id');

        $this->applyRoleFilter($query, $role);

        return $query->paginate($perPage);
    }

    public function getUserByOTP(string $OTP, string $type): User
    {
        $otp = OtpCode::where('type', 'password_reset')
            ->where('used', false)
            ->get()
            ->first();

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => ['Invalid OTP'],
            ]);
        }

        $user = User::find($otp->user_id);

        return $user;
    }

    public function updateOtpMeta(User $user, array $data): bool
    {
        return $user->update($data);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function updateOtp(User $user, array $data): bool
    {
        return $user->update($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    private function applyRoleFilter(Builder $query, ?string $role): void
    {
        if ($role === null) {
            return;
        }

        if ($role === 'student') {
            $query
                ->role('student')
                ->whereDoesntHave('roles', function (Builder $rolesQuery): void {
                    $rolesQuery->whereIn('name', [
                        'supervisor',
                        'supervisor_lead',
                    ]);
                });

            return;
        }

        $query->role($role);
    }
}
