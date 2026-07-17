<?php

namespace App\Repositories;

use App\Interfaces\PointRepositoryInterface;
use App\Models\PointActionType;
use App\Models\PointRule;
use App\Models\PointTransaction;
use Illuminate\Database\Eloquent\Model;

class PointRepository implements PointRepositoryInterface
{
    public function findActiveActionTypeByRuleCode(string $actionType): ?PointActionType
    {
        $rule = PointRule::query()
            ->where('action_type', $actionType)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            return null;
        }

        return PointActionType::query()
            ->with('rule')
            ->where('point_rule_id', $rule->id)
            ->first();
    }

    public function userReachedDailyLimit(
        int $userId,
        int $pointActionTypeId,
        int $maxPerDay
    ): bool {
        $todayCount = PointTransaction::query()
            ->where('user_id', $userId)
            ->where('point_action_type_id', $pointActionTypeId)
            ->whereDate('created_at', today())
            ->count();

        return $todayCount >= $maxPerDay;
    }

    public function transactionExists(
        int $userId,
        int $pointActionTypeId,
        Model $reference
    ): bool {
        return PointTransaction::query()
            ->where('user_id', $userId)
            ->where('point_action_type_id', $pointActionTypeId)
            ->where('reference_type', $reference::class)
            ->where('reference_id', $reference->getKey())
            ->exists();
    }

    public function createTransaction(
        int $userId,
        int $points,
        int $pointActionTypeId,
        Model $reference,
        ?string $description = null
    ): PointTransaction {
        return PointTransaction::query()->create([
            'user_id' => $userId,
            'points' => $points,
            'point_action_type_id' => $pointActionTypeId,
            'reference_type' => $reference::class,
            'reference_id' => $reference->getKey(),
            'description' => $description,
        ]);
    }

    public function getUserTotalPoints(int $userId): int
    {
        return (int) PointTransaction::query()
            ->where('user_id', $userId)
            ->sum('points');
    }
}
