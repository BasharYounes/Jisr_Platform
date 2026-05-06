<?php
namespace App\Repositories;

use App\Models\StudentProfile;
use App\Interfaces\StudentRepositoryInterface;

class StudentRepository implements StudentRepositoryInterface
{
    public function create(array $data)
    {
        return StudentProfile::create($data);
    }
}
