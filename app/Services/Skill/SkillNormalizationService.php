<?php

namespace App\Services\Skills;

use App\Models\Skill;
use App\Models\SkillAlias;
use Illuminate\Support\Str;

class SkillNormalizationService
{
    public function normalizeOne(string $rawSkillName): ?Skill
    {
        $normalizedInput = $this->normalizeText($rawSkillName);

        if (empty($normalizedInput)) {
            return null;
        }

        // dd($normalizedInput);

        // 1) match against NormalizedName
        $skill = Skill::query()
            ->whereRaw('LOWER(normalized_name) = ?', [$normalizedInput])
            ->first();

        if ($skill) {
            return $skill;
        }

        // 2) match against Name
        $skill = Skill::query()
            ->whereRaw('LOWER(name) = ?', [$normalizedInput])
            ->first();

        if ($skill) {
            return $skill;
        }

        // 3) match against aliases
        $alias = SkillAlias::query()
            ->whereRaw('LOWER(Alias) = ?', [$normalizedInput])
            ->first();

        if ($alias) {
            return $alias->skill;
        }

        // 4) fallback: loose partial match in aliases
        $alias = SkillAlias::query()
            ->whereRaw('LOWER(Alias) LIKE ?', ['%'.$normalizedInput.'%'])
            ->first();

        if ($alias) {
            return $alias->skill;
        }

        // 5) fallback: loose partial match in names
        $skill = Skill::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$normalizedInput.'%'])
            ->first();

        return $skill;
    }

    public function normalizeMany(array $rawSkills): array
    {
        $results = [];

        foreach ($rawSkills as $rawSkill) {
            $rawName = is_array($rawSkill)
                ? ($rawSkill['skill_name'] ?? '')
                : (string) $rawSkill;

            $skill = $this->normalizeOne($rawName);

            $results[] = [
                'raw_skill_name' => $rawName,
                'matched' => $skill !== null,
                'skill_id' => $skill?->id,
                'skill_name' => $skill?->name,
                'normalized_name' => $skill?->normalized_name,
            ];
        }

        return $results;
    }

    private function normalizeText(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);

        return $value ?? '';
    }
}
