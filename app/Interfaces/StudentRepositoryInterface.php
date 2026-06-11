<?php

namespace App\Interfaces;

use App\Models\StudentProfile;
use App\Models\User;

interface StudentRepositoryInterface
{
    public function create(array $data);

    public function findByUser(User $user): ?StudentProfile;

    public function update(StudentProfile $studentProfile, array $data): StudentProfile;
}
