<?php

namespace App\Services\Matching;

use Illuminate\Support\Collection;

class SkillMatchService
{
    private const PARTIAL_MATCH_RATIO = 0.50;

    /**
     * @param  Collection  $requiredSkills  Skills with pivot: required_level, mandatory, weight
     * @param  Collection  $studentSkills  UserSkill collection indexed by SkillId
     */
    public function calculate(
        Collection $requiredSkills,
        Collection $studentSkills
    ): array {
        if ($requiredSkills->isEmpty()) {
            return [
                'score' => 0.0,
                'reasons' => [
                    'لا توجد مهارات مطلوبة لهذه الفرصة. | No required skills found for this opportunity.',
                ],
                'matched_skills' => [],
                'missing_skills' => [],
                'matched_skills_count' => 0,
                'partially_matched_skills_count' => 0,
                'total_skills_count' => 0,
                'missing_mandatory_skills' => [],
                'is_eligible_for_recommendation' => false,
            ];
        }

        $totalWeight = 0.0;
        $earnedWeight = 0.0;

        $matchedSkills = [];
        $missingSkills = [];
        $missingMandatorySkills = [];
        $reasons = [];

        $matchedSkillsCount = 0;
        $partiallyMatchedSkillsCount = 0;

        foreach ($requiredSkills as $skill) {
            $weight = (float) ($skill->pivot->weight ?? 1.00);
            $requiredLevel = $skill->pivot->required_level !== null
                ? (int) $skill->pivot->required_level
                : null;

            $isMandatory = (bool) ($skill->pivot->mandatory ?? false);

            $totalWeight += $weight;

            $studentSkill = $studentSkills->get($skill->id);

            if (! $studentSkill) {
                $missingSkills[] = [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'required_level' => $requiredLevel,
                    'mandatory' => $isMandatory,
                    'weight' => $weight,
                    'reason' => 'missing',
                ];

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

                $matchedSkills[] = [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'required_level' => $requiredLevel,
                    'student_level' => $studentLevel,
                    'mandatory' => $isMandatory,
                    'weight' => $weight,
                    'match_type' => 'partial',
                ];

                $reasons[] = "يمتلك الطالب {$skill->name} لكن بمستوى أقل من المطلوب. | Student has {$skill->name}, but below the required level.";

                continue;
            }

            $earnedWeight += $weight;
            $matchedSkillsCount++;

            $matchedSkills[] = [
                'id' => $skill->id,
                'name' => $skill->name,
                'required_level' => $requiredLevel,
                'student_level' => $studentLevel,
                'mandatory' => $isMandatory,
                'weight' => $weight,
                'match_type' => 'full',
            ];

            $reasons[] = "يمتلك الطالب المهارة المطلوبة {$skill->name} بالمستوى المناسب. | Student matches the required skill: {$skill->name}.";
        }

        $score = $totalWeight > 0
            ? round(($earnedWeight / $totalWeight) * 100, 2)
            : 0.0;

        return [
            'score' => $score,
            'reasons' => $reasons,
            'matched_skills' => $matchedSkills,
            'missing_skills' => $missingSkills,
            'matched_skills_count' => $matchedSkillsCount,
            'partially_matched_skills_count' => $partiallyMatchedSkillsCount,
            'total_skills_count' => $requiredSkills->count(),
            'missing_mandatory_skills' => $missingMandatorySkills,
            'is_eligible_for_recommendation' => empty($missingMandatorySkills),
        ];
    }
}
