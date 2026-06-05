<?php

namespace App\Repositories;

use App\Interfaces\SupervisorRepositoryInterface;
use App\Models\SupervisorProfile;

class SupervisorRepository implements SupervisorRepositoryInterface
{
    public function create(array $data)
    {
        return SupervisorProfile::create($data);
    }
}
