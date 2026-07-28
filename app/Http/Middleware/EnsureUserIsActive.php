<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /*
         * نستدعي Sanctum مباشرة حتى يعمل الفحص
         * حتى لو نُفذ هذا الـMiddleware قبل auth:sanctum.
         */
        $user = $request->user('sanctum');

        if (
            $user !== null
            && ! (bool) $user->is_active
        ) {
            return ApiResponse::error(
                'Your account is blocked and cannot access the system.',
                403
            );
        }

        return $next($request);
    }
}
