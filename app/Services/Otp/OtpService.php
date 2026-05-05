<?php

namespace App\Services\Otp;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
// use Nette\Schema\ValidationException;
use Illuminate\Validation\ValidationException;  


class OtpService
{
    /**
     * Generate OTP for login
     */
  public function generateLoginOtp(User $user): array
{
    if ($user->otpCodes()->where('created_at', '>', now()->subMinute())->exists()) {
        throw new \Exception('Too many requests');
    }

    $user->otpCodes()
        ->where('type', 'login')
        ->where('used', false)
        ->delete();

    $code = random_int(100000, 999999);

    $user->otpCodes()->create([
        'code' => Hash::make($code),
        'type' => 'login',
        'expires_at' => now()->addMinutes(5),
        'used' => false,
    ]);

    return [
        'plain_code' => $code,
    ];
}

public function verifyOtpByType(User $user, string $code, string $type): bool{
    $otp = $user->otpCodes()
        ->where('type', $type)
        ->where('used', false)
        ->where('expires_at', '>', now())
        ->latest()
        ->first();

    if (! $otp) {
        return false;
    }

    if (! Hash::check($code, $otp->code)) {
        return false;
    }

    $otp->update(['used' => true]);

    return true;
}

public function generateResetOtp(User $user): array
{
    $user->otpCodes() ->where('type', 'password_reset') 
    ->where('used', false)
     ->delete(); 
     $plainCode = (string) random_int(100000, 999999); 
     $otp = $user->otpCodes()->create([ 'code' => Hash::make($plainCode), 
     'type' => 'password_reset', 'expires_at' => now()->addMinutes(10), 'used' => false, ]);
       return [ 
        'otp' => $otp, 
        'plain_code' => $plainCode, 
        ]; 
    }

    public function verifyOtpByReset(User $user, string $code, string $type): string
    {
        $this->verifyOtpByType($user, $code, $type);
        $token = $user->createToken('api-token')->plainTextToken;
        return $token;
    }

}