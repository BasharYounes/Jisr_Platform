<?php

namespace App\Services\Auth;

use App\Events\LoginOtpRequested;
use App\Events\PasswordResetOtpRequested;
use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\SendOtpNotification;
use App\Repositories\UserRepository;
use App\Services\Otp\OtpService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;  
use Carbon\Carbon;


class AuthService
{
    protected UserRepository $userRepository;
    protected OtpService $otpService;
    

    public function __construct(UserRepository $userRepository, OtpService $otpService)
    {
        $this->userRepository = $userRepository;
        $this->otpService = $otpService;

    }

public function registerFromRequest(Request $request): array
{
  $data = $request->validated();
  $data['profile_picture'] = $request->file('profile_picture');

 return $this->register(
        $request->input('role'),
        $data
    );
}

public function register(string $role, array $data): array
{
    
    $strategy = RegisterStrategyFactory::make($role);

  
    return $strategy->register($data);
}

public function login(array $data): array
{
  $user = $this->userRepository->findByEmailOrFail($data['email']);
    if (! Hash::check($data['password'], $user->password)) {
        throw ValidationException::withMessages([
         'email' => 'Invalid email or password', 
         ]);
    }

   if ($user->hasRole('company')) {
    if ($user->is_verified_by_admin === 'pending') {
        throw ValidationException::withMessages([
            'email' => ['Your account is still pending admin verification.'],
        ]);
    }

    if ($user->is_verified_by_admin === 'rejected') {
        throw ValidationException::withMessages([
            'email' => ['Your company account has been rejected. Please sign up again and upload valid verification documents.'],
        ]);
    }
}
    $otpData =$this->otpService->generateLoginOtp($user);

     event(new LoginOtpRequested(
    user: $user,
    code: $otpData['plain_code']
));

    return [
        'message' => 'OTP sent to your email',
        'requires_otp' => true,
    ];
}

public function verifyLoginOtp(array $data): array
{
    $user = $this->userRepository->findByEmailOrFail($data['email']);

    if (! $this->otpService->verifyOtpByType($user, $data['code'], 'login')) {
        throw ValidationException::withMessages([
            'code' => ['OTP Expired or invalid'],
        ]);
    }

    $token = $user->createToken('api-token')->plainTextToken;

    return [
        'user' => $user->load('roles'),
        'token' => $token,
    ];
}


public function forgetPassword(string $email): array
{
    $user = $this->userRepository->findByEmailOrFail($email);

    $otpData = $this->otpService->generateResetOtp($user);

    event(new PasswordResetOtpRequested(
        user: $user,
        code: $otpData['plain_code']
    ));

    return [
        'message' => 'OTP sent to your email',
    ];
}

 public function getUserByOTP(string $OTP): User
    {
        return $this->userRepository->getUserByOTP($OTP, 'password_reset');
    }

    public function findByEmailOrFail(string $email): User
    {
        return $this->userRepository->findByEmailOrFail($email);
    }

    public function verifyPasswordResetOtp(array $data): array
    {
    $user = $this->userRepository->findByEmailOrFail($data['email']);
    $attempts = $user->otp_attempts ?? 0; 
    if (! $this->otpService->verifyOtpByType($user, $data['code'], 'password_reset')) {
        throw ValidationException::withMessages([
            'code' => ['OTP Expired or invalid'],
        ]);
    }

$this->userRepository->updateOtpMeta($user, [
    'otp_last_sent_at' => now(),
    'otp_attempts' => $attempts + 1,
]);

$token = $user->createToken('api-token')->plainTextToken;

return [
    'user' => $user->load('roles'),
    'token' => $token,
];
}


public function resetPassword(array $data): array
{
    $user = Auth::user();
    $user->update([
        'password' => Hash::make($data['new_password']),
    ]);

    $user->tokens()->delete();
    
    return [
        'message' => 'Password reset successfully',
    ];
    }



public function resendOtp(array $data): void
{
    $user = $this->userRepository->findByEmailOrFail($data['email']);

    $attempts = $user->otp_attempts ?? 0;

    $waitMinutes = 5 * (2 ** max(0, $attempts - 1));

    if ($user->otp_last_sent_at) {

        $nextAllowedAt = Carbon::parse($user->otp_last_sent_at)
            ->addMinutes($waitMinutes);

        if (now()->lessThan($nextAllowedAt)) {

            $remainingMinutes = now()->diffInMinutes($nextAllowedAt) + 1;

            throw ValidationException::withMessages([
                'otp' => [
                    "Please wait {$remainingMinutes} minutes before requesting another OTP."
                ],
            ]);
        }
    }

    $this->userRepository->updateOtpMeta($user, [
        'otp_last_sent_at' => now(),
        'otp_attempts' => $attempts + 1,
    ]);

    $otpData = $this->otpService->generateResetOtp($user);

    event(new PasswordResetOtpRequested(
        user: $user,
        code: $otpData['plain_code']
    ));
}

public function logout(): array
{
    $user = Auth::user();

    $user->currentAccessToken()->delete();

    return [
        'message' => 'Logged out successfully',
    ];
}   

public function logoutAll()
{
    $user = Auth::user();

    $user->tokens()->delete();

    return [
        'status' => true,
        'message' => 'Logged out from all devices'
    ];
}


  }