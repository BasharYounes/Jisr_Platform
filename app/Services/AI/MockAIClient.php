<?php

namespace App\Services\AI;

class MockAIClient implements AIClientInterface
{
    public function generateJson(string $systemPrompt, string $userPrompt): array
    {
        if (str_contains($systemPrompt, 'CV skill extraction')) {
            return [
                'skills' => [
                    [
                        'skill_name' => 'Python',
                        'evidence' => 'Mentioned Python in projects and backend development tasks.',
                        'initial_level' => 2.5,
                        'confidence' => 0.80,
                    ],
                    [
                        'skill_name' => 'Flask',
                        'evidence' => 'Mentioned building APIs using Flask.',
                        'initial_level' => 2.0,
                        'confidence' => 0.76,
                    ],
                    [
                        'skill_name' => 'SQL',
                        'evidence' => 'Mentioned database queries and relational databases.',
                        'initial_level' => 1.8,
                        'confidence' => 0.72,
                    ],
                    [
                        'skill_name' => 'Git',
                        'evidence' => 'Mentioned using GitHub and commit/push workflow.',
                        'initial_level' => 1.5,
                        'confidence' => 0.70,
                    ],
                ],
            ];
        }

        return [
            'criteria_results' => [
                [
                    'criterion_name' => 'الفكرة الأساسية',
                    'score' => 2,
                    'max_score' => 2,
                    'comment' => 'الإجابة تضمنت الفكرة الأساسية.',
                ],
                [
                    'criterion_name' => 'مثال أو تطبيق عملي',
                    'score' => 1,
                    'max_score' => 2,
                    'comment' => 'المثال موجود لكنه مختصر.',
                ],
                [
                    'criterion_name' => 'الوضوح والدقة',
                    'score' => 1,
                    'max_score' => 1,
                    'comment' => 'الإجابة واضحة.',
                ],
            ],
            'total_score' => 4,
            'max_score' => 5,
            'normalized_score' => 0.8,
            'feedback_ar' => 'إجابتك جيدة ومباشرة، لكن تحتاج إلى مثال أكثر تفصيلًا.',
            'confidence' => 0.82,
        ];
    }
}
