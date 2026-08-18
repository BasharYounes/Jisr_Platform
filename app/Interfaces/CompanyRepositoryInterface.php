<?php

namespace App\Interfaces;

use App\Models\Company;

interface CompanyRepositoryInterface
{
    public function create(array $data);

    public function findById(int $companyId): Company;
}
