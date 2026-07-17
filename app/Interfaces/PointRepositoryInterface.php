<?php

namespace App\Interfaces;

use App\Models\PointActionType;
use App\Models\PointTransaction;
use Illuminate\Database\Eloquent\Model;

interface PointRepositoryInterface
{
    public function findActiveActionTypeByRuleCode(string $actionType): ?PointActionType;

    public function userReachedDailyLimit(
        int $userId,
        int $pointActionTypeId,
        int $maxPerDay
    ): bool;

    public function transactionExists(
        int $userId,
        int $pointActionTypeId,
        Model $reference
    ): bool;

    public function createTransaction(
        int $userId,
        int $points,
        int $pointActionTypeId,
        Model $reference,
        ?string $description = null
    ): PointTransaction;

    public function getUserTotalPoints(int $userId): int;
}
