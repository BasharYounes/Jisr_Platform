<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\StudentSkillRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TaskRecommendationService
{
    public function __construct(
        private readonly StudentSkillRepositoryInterface $studentSkillRepository
    ) {}

    public function rankTasksForStudent(int $studentUserId, EloquentCollection $tasks): Collection
    {
        $studentSkills = $this->studentSkillRepository->getSkillsForStudent($studentUserId);

        return $tasks
            ->map(function ($task) use ($studentSkills) {
                $result = $this->calculateSkillMatch($task, $studentSkills);

                $task->match_score = $result['score'];
                $task->match_reasons = $result['reasons'];

                return $task;
            })
            ->filter(fn ($task) => $task->match_score >= 50)
            ->sortByDesc('match_score')
            ->values();
    }

    private function calculateSkillMatch(Model $task, Collection $studentSkills): array
    {
        $taskSkills = $task->skills;

        if ($taskSkills->isEmpty()) {
            return [
                'score' => 0,
                'reasons' => [
                    'لا توجد مهارات مطلوبة لهذه المهمة. | No required skills found for this task.',
                ],
            ];
        }

        $totalWeight = 0;
        $matchedWeight = 0;
        $reasons = [];

        foreach ($taskSkills as $skill) {
            $weight = (float) ($skill->pivot->weight ?? 1);
            $requiredLevel = $skill->pivot->required_level;

            $totalWeight += $weight;

            $studentSkill = $studentSkills->get($skill->id);

            if (! $studentSkill) {
                $reasons[] = "المهارة {$skill->name} غير موجودة في ملف الطالب. | Missing skill: {$skill->name}.";
                continue;
            }

            $studentLevel = $studentSkill->ProficiencyLevel  ?? 0;

            if ($requiredLevel !== null && $studentLevel < $requiredLevel) {
                $partialWeight = $weight * 0.5;
                $matchedWeight += $partialWeight;

                $reasons[] = "يمتلك الطالب {$skill->name} لكن بمستوى أقل من المطلوب. | Student has {$skill->name}, but below required level.";
                continue;
            }

            $matchedWeight += $weight;

            $reasons[] = "يمتلك الطالب المهارة المطلوبة {$skill->name}. | Matched required skill: {$skill->name}.";
        }

        $score = $totalWeight > 0
            ? round(($matchedWeight / $totalWeight) * 100, 2)
            : 0;

        return [
            'score' => $score,
            'reasons' => $reasons,
        ];
    }
}