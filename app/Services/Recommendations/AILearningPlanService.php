<?php

namespace App\Services\Recommendations;

use App\Models\AILearningPlan;
use App\Models\AssessmentSession;
use App\Services\AI\AIClientInterface;

class AILearningPlanService
{
    public function __construct(
        private readonly LearningPathService $learningPathService,
        private readonly AIClientInterface $aiClient
    ) {
    }

    public function generate(
        AssessmentSession $session,
        int $weeks = 4,
        int $hoursPerWeek = 5
    ): AILearningPlan {
        $learningPath = $this->learningPathService->generate($session);

        $inputSnapshot = [
            'assessment_session_id' => $session->AssessmentSessionID,
            'career_path_id' => $session->CareerPathID,
            'weeks' => $weeks,
            'hours_per_week' => $hoursPerWeek,
            'learning_path' => $learningPath,
        ];

        $plan = $this->generateWithAI($inputSnapshot, $session->CareerPath->Name);

        return AILearningPlan::query()->create([
            'AssessmentSessionID' => $session->AssessmentSessionID,
            'UserID' => $session->UserID,
            'Status' => 'generated',
            'Weeks' => $weeks,
            'HoursPerWeek' => $hoursPerWeek,
            'InputSnapshotJson' => $inputSnapshot,
            'PlanJson' => $plan,
            'SummaryText' => $plan['summary_ar'] ?? null,
            'AiModelVersion' => config('services.gemini.model', 'mock'),
            'GeneratedAt' => now(),
        ]);
    }

    private function generateWithAI(array $inputSnapshot, string $careerPathName): array
    {
        $systemPrompt = <<<PROMPT
            You are an AI learning plan generator for students on the Jisr Platform.

            Return valid JSON only. Do not use markdown. Do not wrap the response in code fences.

            Your task:
            Create a practical Arabic weekly learning plan for the given career path based on skill gaps and recommended resources.

            Career path:
            {$careerPathName}

            Platform context:
            Jisr Platform helps students bridge the gap between university and the job market through skill assessment, project-based learning, mentorship, and personalized recommendations.

            Rules:
            1. Return Arabic content.
            2. Respect the given career path.
            3. Respect the given weeks and hours_per_week.
            4. Focus on the highest priority gaps first.
            5. Use only the provided skills and resources.
            6. Do not invent external resources or URLs.
            7. Make the tasks suitable for a student preparing for the job market.
            8. Each week must include:
            - focus_skills
            - goals
            - tasks
            - resources
            - expected_outcome
            9. The plan must be practical, gradual, and realistic.
            10. The output must follow the exact JSON schema.
        PROMPT;

        $userPrompt = <<<PROMPT
    Input data:
    {$this->toJson($inputSnapshot)}

    Return JSON in this exact format:
    {
    "career_path": "{$careerPathName}",
    "summary_ar": "خطة مختصرة باللغة العربية",
    "weeks": [
        {
        "week_number": 1,
        "focus_skills": ["Skill name"],
        "goals": [
            "هدف واضح قابل للتنفيذ"
        ],
        "tasks": [
            {
            "title": "مهمة عملية واضحة",
            "estimated_hours": 2,
            "skill": "Skill name"
            }
        ],
        "resources": [
            {
            "title": "Resource title",
            "url": "https://example.com",
            "type": "video",
            "skill": "Skill name"
            }
        ],
        "expected_outcome": "نتيجة متوقعة واضحة من هذا الأسبوع"
        }
    ],
    "final_outcome_ar": "ما المتوقع أن يصبح الطالب قادرًا عليه بعد انتهاء الخطة"
    }
    PROMPT;

        return $this->aiClient->generateJson($systemPrompt, $userPrompt);
    }

    private function toJson(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
