<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\VerifyLoginOtpRequest;
use App\Services\Auth\AuthService;
use App\Services\Otp\OtpService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private OtpService $otpService
    ) {}

    public function register(RegisterRequest $request)
    {
        return response()->json(
            $this->authService->registerFromRequest($request),
            201
        );
    }

    public function login(LoginRequest $request)
    {
        return response()->json(
            $this->authService->login($request->validated())
        );
    }

    public function verifyLoginOtp(VerifyLoginOtpRequest $request)
    {
        return response()->json(
            $this->authService->verifyLoginOtp($request->validated())
        );
    }

    public function forgetPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $response = $this->authService->forgetPassword($request->email);

        return response()->json($response);
    }

    public function verifyOTPresetPassword(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $response = $this->authService->verifyPasswordResetOtp($request->all());

        return $response;
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $response = $this->authService->resetPassword($request->all());

        return response()->json($response);
    }

    public function resendOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);
        $this->authService->resendOtp($data);

        return response()->json([
            'status' => true,
            'message' => 'OTP resent successfully.',
        ]);
    }

    public function logout()
    {
        return response()->json(
            $this->authService->logout()
        );
    }

    public function logoutAll()
    {
        return response()->json(
            $this->authService->logoutAll()
        );
    }
}
