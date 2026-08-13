<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        $statistics = [
            'published_opportunities' => Opportunity::query()
                ->where('status', 'published')
                ->count(),

            'active_users' => User::query()
                ->where('is_active', true)
                ->count(),

            'blocked_users' => User::query()
                ->where('is_active', false)
                ->count(),

            'total_companies' => Company::query()->count(),

            'total_supervisors' => User::query()
                ->whereHas('roles', function ($query): void {
                    $query
                        ->where('name', 'supervisor')
                        ->where('guard_name', 'web');
                })
                ->count(),
        ];

        return $this->success(
            'تم جلب إحصائيات لوحة التحكم بنجاح. | Admin dashboard statistics retrieved successfully.',
            $statistics
        );
    }
}
