<?php

namespace App\Repositories;

use App\Interfaces\StudentRepositoryInterface;
use App\Models\StudentProfile;
use App\Models\User;

class StudentRepository implements StudentRepositoryInterface
{
    public function create(array $data)
    {
        return StudentProfile::create($data);
    }

    public function findByUser(User $user): ?StudentProfile
    {
        return $user->studentProfile()
            ->with('user')
            ->first();
    }

    public function update(StudentProfile $studentProfile, array $data): StudentProfile
    {
        $studentProfile->update($data);

        return $studentProfile->fresh('user');
    }
}
