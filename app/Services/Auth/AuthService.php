<?php

namespace App\Services\Auth;

use App\Events\LoginOtpRequested;
use App\Events\PasswordResetOtpRequested;
use App\Models\OtpCode;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\Otp\OtpService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;  


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
    if (! $user || ! Hash::check($data['password'], $user->password)) {
        throw ValidationException::withMessages([
         'email' => 'Invalid email or password', 
         ]);
    }

    if ($user->hasRole('company') && $user->is_verified_by_admin != 'accepted') {
        throw ValidationException::withMessages([
            'email' => 'Your account is not verified by admin yet',
        ]);
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

public function generateResetOtp(User $user): array
{
    $user->otpCodes()
        ->where('type', 'password_reset')
        ->where('used', false)
        ->delete();

    $plainCode = (string) random_int(100000, 999999);

    $otp = $user->otpCodes()->create([
        'code' => Hash::make($plainCode),
        'type' => 'password_reset',
        'expires_at' => now()->addMinutes(10),
        'used' => false,
    ]);
      
    return [
        'otp' => $otp,
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

    if (! $this->otpService->verifyOtpByType($user, $data['code'], 'password_reset')) {
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


    public function logout()
{
    $user = Auth::user();
    $user->currentAccessToken()->delete();

    return [
        'status' => true,
        'message' => 'Logged out successfully'
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