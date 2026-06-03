<?php

namespace App\Services\Auth\Strategies;

use App\Events\UserRegistered;
use App\Interfaces\SupervisorRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SupervisorRegisterStrategy implements RegisterStrategyInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
        private SupervisorRepositoryInterface $supervisorRepo,
    ) {}

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = $this->userRepo->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole('supervisor');

            $supervisorProfile = $this->supervisorRepo->create([
                'user_id' => $user->id,
                'specialization' => $data['specialization'],
                'is_volunteer' => (bool) ($data['is_volunteer'] ?? false),
            ]);

            $token = $user->createToken('api-token')->plainTextToken;

            DB::afterCommit(function () use ($user, $supervisorProfile) {
                event(new UserRegistered(
                    user: $user,
                    profile: $supervisorProfile,
                    role: 'supervisor'
                ));
            });

            return [
                'user' => $user,
                'supervisor_profile' => $supervisorProfile,
                'token' => $token,
            ];
        });
    }
}
