<?php

namespace App\Services\AI;

use App\Models\QuestionBank;

class AnswerEvaluationService
{
    public function __construct(
        private readonly AIClientInterface $aiClient
    ) {}

    public function evaluate(QuestionBank $question, string $studentAnswer): array
    {
        $rubricJson = $question->rubrics->map(function ($rubric) {
            return [
                'criterion_name' => $rubric->CriterionName,
                'description' => $rubric->CriterionDescription,
                'max_score' => (float) $rubric->MaxScore,
                'weight' => (float) $rubric->Weight,
                'keywords' => $rubric->KeywordsJson,
                'sample_good_answer' => $rubric->SampleGoodAnswer,
                'sample_bad_answer' => $rubric->SampleBadAnswer,
            ];
        })->values()->toJson(JSON_UNESCAPED_UNICODE);

        $systemPrompt = <<<'PROMPT'
            You are a strict technical assessor.

            Your task is to evaluate a student's answer to a technical question using the provided rubric.

            Rules:
            1. Return JSON only.
            2. Score each rubric criterion separately.
            3. Do not give credit for information not present in the student's answer.
            4. Be strict, fair, and evidence-based.
            5. Provide a short Arabic feedback message.
            6. Provide a normalized_score between 0 and 1.
            7. The total score must be based only on the rubric.

            Return valid JSON only. Do not use markdown. Do not wrap the response in code fences.
        PROMPT;

        $userPrompt = <<<PROMPT
            Skill: {$question->skill->name}
            Question level: {$question->Level}

            Question:
            {$question->QuestionText}

            Rubric:
            {$rubricJson}

            Student answer:
            {$studentAnswer}

            Return JSON:
            {
            "criteria_results": [
                {
                "criterion_name": "الفكرة الأساسية",
                "score": 2,
                "max_score": 2,
                "comment": "ذكر المفهوم الأساسي بشكل صحيح"
                }
            ],
            "total_score": 4,
            "max_score": 5,
            "normalized_score": 0.8,
            "feedback_ar": "إجابتك جيدة لكن يمكن تحسين المثال.",
            "confidence": 0.82
            }
        PROMPT;

        return $this->aiClient->generateJson(
            $systemPrompt,
            $userPrompt,
            'reasoning'
        );
    }
}
