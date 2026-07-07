<?php

namespace App\Services\Points;

use App\Interfaces\PointRepositoryInterface;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class PointService
{
    public function __construct(
        private readonly PointRepositoryInterface $pointRepository
    ) {}

    public function award(
        User $user,
        string $actionType,
        Model $reference,
        ?string $description = null,
        bool $preventDuplicate = true
    ): ?PointTransaction {
        $pointActionType = $this->pointRepository
            ->findActiveActionTypeByRuleCode($actionType);

        if (! $pointActionType || ! $pointActionType->rule) {
            return null;
        }

        $rule = $pointActionType->rule;

        if ($preventDuplicate) {
            $alreadyExists = $this->pointRepository->transactionExists(
                userId: $user->id,
                pointActionTypeId: $pointActionType->id,
                reference: $reference
            );

            if ($alreadyExists) {
                return null;
            }
        }

        if ($rule->max_per_day !== null) {
            $reachedLimit = $this->pointRepository->userReachedDailyLimit(
                userId: $user->id,
                pointActionTypeId: $pointActionType->id,
                maxPerDay: (int) $rule->max_per_day
            );

            if ($reachedLimit) {
                return null;
            }
        }

        return $this->pointRepository->createTransaction(
            userId: $user->id,
            points: (int) $rule->points,
            pointActionTypeId: $pointActionType->id,
            reference: $reference,
            description: $description
        );
    }

    public function totalForUser(User $user): int
    {
        return $this->pointRepository->getUserTotalPoints($user->id);
    }

    public function getUserTotalPoints(User $user): int
    {
        return (int) PointTransaction::query()
            ->where('user_id', $user->id)
            ->sum('points');
    }

    public function getUserPointTransactions(User $user, array $filters = []): LengthAwarePaginator
    {
        return PointTransaction::query()
            ->with([
                'actionType.rule',
                'actionType.category',
            ])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($filters['per_page'] ?? 10);
    }
}
