<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\StudentSkillRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class TaskRecommendationService
{
    private const MINIMUM_RECOMMENDATION_SCORE = 50.0;

    public function __construct(
        private readonly StudentSkillRepositoryInterface $studentSkillRepository,
        private readonly TaskSkillMatchService $taskSkillMatchService
    ) {}

    public function rankTasksForStudent(
        int $studentUserId,
        EloquentCollection $tasks
    ): Collection {
        $studentSkills = $this->studentSkillRepository
            ->getSkillsForStudent($studentUserId);

        return $tasks
            ->map(function ($task) use ($studentSkills) {
                $matchResult = $this->taskSkillMatchService->calculate(
                    task: $task,
                    studentSkills: $studentSkills
                );

                if (
                    ! $matchResult['is_eligible_for_recommendation']
                    || $matchResult['score'] < self::MINIMUM_RECOMMENDATION_SCORE
                ) {
                    return null;
                }

                /*
                 * هدول الحقلين كانوا موجودين أصلًا بالـ response
                 * تبع Recommended، لذلك ما عم نغيّر API Contract.
                 */
                $task->match_score = $matchResult['score'];
                $task->match_reasons = $matchResult['reasons'];

                return $task;
            })
            ->filter()
            ->sortByDesc('match_score')
            ->values();
    }
}
