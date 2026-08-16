<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsVerified
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user('sanctum');

        if (
            $user !== null
            && $user->hasRole('company')
            && $user->is_verified_by_admin !== 'accepted'
        ) {
            return ApiResponse::error(
                'Your company account is not verified by admin.',
                403
            );
        }

        return $next($request);
    }
}
