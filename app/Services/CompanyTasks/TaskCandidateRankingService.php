<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\StudentSkillRepositoryInterface;
use App\Models\CompanyTask;

class TaskCandidateRankingService
{
    public function __construct(
        private readonly StudentSkillRepositoryInterface $studentSkillRepository,
        private readonly TaskSkillMatchService $taskSkillMatchService
    ) {}

    /**
     * Calculate and prepare the matching snapshot saved with an application.
     *
     * @return array{
     *     match_score: float,
     *     match_reasons: array<int, string>
     * }
     */
    public function calculateApplicationSnapshot(
        CompanyTask $task,
        int $studentUserId
    ): array {
        $studentSkills = $this->studentSkillRepository
            ->getSkillsForStudent($studentUserId);

        $matchResult = $this->taskSkillMatchService->calculate(
            task: $task,
            studentSkills: $studentSkills
        );

        return [
            'match_score' => $matchResult['score'],
            'match_reasons' => $matchResult['reasons'],
        ];
    }
}
