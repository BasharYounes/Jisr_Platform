<?php
namespace App\Interfaces;

use App\Models\User;

interface UserRepositoryInterface
{
    public function create(array $data);
    public function findByEmailOrFail(string $email): User;
    public function listUsers();
    public function getUserByOTP(string $OTP,string $type): User;
}
