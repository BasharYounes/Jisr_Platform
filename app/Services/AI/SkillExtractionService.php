<?php

namespace App\Services\AI;

class SkillExtractionService
{
    public function __construct(
        private readonly AIClientInterface $aiClient
    ) {
    }

    public function extractSkills(string $resumeText, string $careerPath = 'Backend Developer'): array
    {
        $systemPrompt = <<<PROMPT
            You are an expert CV skill extraction engine.

            Return valid JSON only. Do not use markdown. Do not wrap the response in code fences.

            Your task is to extract only these target technical skills from a student's resume:
            - Python
            - Flask
            - SQL
            - Git

            For each skill, estimate an initial proficiency level from 0 to 5 based only on evidence in the resume.

            Rules:
            1. Return JSON only.
            2. Do not invent evidence.
            3. If a target skill is not mentioned, do not include it.
            4. If a skill is only listed with no evidence, assign level between 0.5 and 1.5.
            5. initial_level must be a number between 0 and 5.
            6. confidence must be a number between 0 and 1.
            7. evidence must be short and copied or paraphrased from the resume.
        PROMPT;

        $userPrompt = <<<PROMPT
            Career path: {$careerPath}

            Resume text:
            {$resumeText}

            Return JSON in this exact format:
            {
            "skills": [
                {
                "skill_name": "Python",
                "evidence": "Built simple APIs and university backend projects",
                "initial_level": 2.5,
                "confidence": 0.82
                }
            ]
            }
        PROMPT;

        return $this->aiClient->generateJson($systemPrompt, $userPrompt);
    }
}
