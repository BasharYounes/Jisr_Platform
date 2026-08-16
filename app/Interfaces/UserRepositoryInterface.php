<?php

namespace App\Interfaces;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function create(array $data);

    public function findByEmailOrFail(string $email): User;

    public function listUsers(
        ?string $role = null,
        int $perPage = 20
    ): LengthAwarePaginator;

    public function getUserByOTP(string $OTP, string $type): User;

    public function findByEmail(string $email): ?User;

    public function updateOtp(User $user, array $data): bool;

    public function updateOtpMeta(User $user, array $data): bool;

    public function update(User $user, array $data): User;
}
