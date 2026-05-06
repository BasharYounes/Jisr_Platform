<?php

namespace App\Repositories;

use App\Models\User;
use App\Interfaces\UserRepositoryInterface;
use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Model;
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

   public function listUsers()
  {
    return User::all();
  }
    public function getUserByOTP(string $OTP,string $type): User
  {
   $otp = OtpCode::where('type', 'password_reset')
    ->where('used', false)
    ->get()
    ->first();
     

     if (!$otp) {
    throw ValidationException::withMessages([
        'code' => ['Invalid OTP'],
    ]);
    }

    $user = User::find($otp->user_id);
     return $user;
  }
}