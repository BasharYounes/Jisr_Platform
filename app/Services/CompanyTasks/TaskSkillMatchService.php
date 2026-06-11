<?php

namespace App\Services\CompanyTasks;

use App\Models\CompanyTask;
use Illuminate\Support\Collection;

class TaskSkillMatchService
{
    private const PARTIAL_MATCH_RATIO = 0.5;

    /**
     * Calculate the student's skill match against a specific company task.
     *
     * @return array{
     *     score: float,
     *     reasons: array<int, string>,
     *     matched_skills_count: int,
     *     partially_matched_skills_count: int,
     *     total_skills_count: int,
     *     missing_mandatory_skills: array<int, string>,
     *     is_eligible_for_recommendation: bool
     * }
     */
    public function calculate(CompanyTask $task, Collection $studentSkills): array
    {
        $taskSkills = $task->skills;

        if ($taskSkills->isEmpty()) {
            return [
                'score' => 0.0,
                'reasons' => [
                    'لا توجد مهارات مطلوبة لهذه المهمة. | No required skills found for this task.',
                ],
                'matched_skills_count' => 0,
                'partially_matched_skills_count' => 0,
                'total_skills_count' => 0,
                'missing_mandatory_skills' => [],
                'is_eligible_for_recommendation' => false,
            ];
        }

        $totalWeight = 0.0;
        $earnedWeight = 0.0;

        $matchedSkillsCount = 0;
        $partiallyMatchedSkillsCount = 0;

        $reasons = [];
        $missingMandatorySkills = [];

        foreach ($taskSkills as $skill) {
            $weight = (float) ($skill->pivot->weight ?? 1.00);

            $requiredLevel = $skill->pivot->required_level !== null
                ? (int) $skill->pivot->required_level
                : null;

            $isMandatory = (bool) ($skill->pivot->mandatory ?? false);

            $totalWeight += $weight;

            $studentSkill = $studentSkills->get($skill->id);

            if (! $studentSkill) {
                if ($isMandatory) {
                    $missingMandatorySkills[] = $skill->name;

                    $reasons[] = "المهارة الإلزامية {$skill->name} غير موجودة لدى الطالب. | Missing mandatory skill: {$skill->name}.";
                } else {
                    $reasons[] = "المهارة {$skill->name} غير موجودة لدى الطالب. | Missing skill: {$skill->name}.";
                }

                continue;
            }

            $studentLevel = (int) ($studentSkill->ProficiencyLevel ?? 0);

            if ($requiredLevel !== null && $studentLevel < $requiredLevel) {
                $earnedWeight += $weight * self::PARTIAL_MATCH_RATIO;
                $partiallyMatchedSkillsCount++;

                $reasons[] = "يمتلك الطالب {$skill->name} لكن بمستوى أقل من المطلوب. | Student has {$skill->name}, but below the required level.";

                continue;
            }

            $earnedWeight += $weight;
            $matchedSkillsCount++;

            $reasons[] = "يمتلك الطالب المهارة المطلوبة {$skill->name} بالمستوى المناسب. | Student matches the required skill: {$skill->name}.";
        }

        $score = $totalWeight > 0
            ? round(($earnedWeight / $totalWeight) * 100, 2)
            : 0.0;

        return [
            'score' => $score,
            'reasons' => $reasons,
            'matched_skills_count' => $matchedSkillsCount,
            'partially_matched_skills_count' => $partiallyMatchedSkillsCount,
            'total_skills_count' => $taskSkills->count(),
            'missing_mandatory_skills' => $missingMandatorySkills,
            'is_eligible_for_recommendation' => empty($missingMandatorySkills),
        ];
    }
}
