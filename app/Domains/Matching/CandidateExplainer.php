<?php

namespace App\Domain\Matching;

class CandidateExplainer
{
    public function explain(array $data): array
    {
        $reasons = [];
        $missing = [];

        if ($data['matched_skills'] >= 4) {
            $reasons[] = "Matched {$data['matched_skills']} required skills";
        }

        if ($data['strong_skills']) {
            foreach ($data['strong_skills'] as $skill) {
                $reasons[] = "Strong proficiency in {$skill}";
            }
        }

        if ($data['project_count'] >= 2) {
            $reasons[] = "{$data['project_count']} relevant projects completed";
        }

        if ($data['project_score'] >= 80) {
            $reasons[] = 'Projects rated highly by supervisors';
        }

        if ($data['fresh_days'] <= 7) {
            $reasons[] = 'Active recently';
        }

        if (! empty($data['missing_skills'])) {
            $missing = $data['missing_skills'];
        }

        return [
            'reasons' => $reasons,
            'missing' => $missing,
        ];
    }
}
