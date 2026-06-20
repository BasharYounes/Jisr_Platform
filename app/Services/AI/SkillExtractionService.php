<?php

namespace App\Services\AI;

use App\Models\Skill;

class SkillExtractionService
{
    public function __construct(
        private readonly AIClientInterface $aiClient
    ) {}

    public function extractSkills(string $resumeText, string $careerPath = 'Backend Developer'): array
    {
        $skillsCatalog = $this->buildSkillsCatalog();

        $systemPrompt = $this->buildSystemPrompt($skillsCatalog);
        $userPrompt   = $this->buildUserPrompt($resumeText, $careerPath);

        return $this->aiClient->generateJson($systemPrompt, $userPrompt);
    }

    // ─────────────────────────────────────────────────────────────
    // بناء قائمة المهارات من قاعدة البيانات
    // ─────────────────────────────────────────────────────────────

    /**
     * يجلب كل المهارات مع aliases من DB ويبنيهم كنص منسق للـ prompt.
     *
     * المخرج مثال:
     *   - Python  [also: python3, py, python programming]  (category: Programming Language)
     *   - SQL     [also: mysql, postgresql, postgres]       (category: Database)
     */
    private function buildSkillsCatalog(): string
    {
        $skills = Skill::with('aliases')->get();

        if ($skills->isEmpty()) {
            // fallback لو قاعدة البيانات فارغة لأي سبب
            return '- Python, Flask, SQL, Git';
        }

        $lines = $skills->map(function (Skill $skill) {
            $aliasesText = $skill->aliases->isNotEmpty()
                ? '  [also known as: ' . $skill->aliases->pluck('Alias')->implode(', ') . ']'
                : '';

            return "  - {$skill->name}{$aliasesText}  (category: {$skill->category})";
        });

        return $lines->implode("\n");
    }

    // ─────────────────────────────────────────────────────────────
    // بناء الـ System Prompt
    // ─────────────────────────────────────────────────────────────

    private function buildSystemPrompt(string $skillsCatalog): string
    {
        return <<<PROMPT
            You are an expert CV skill extraction engine for a talent platform.

            Return valid JSON only. Do not use markdown. Do not wrap the response in code fences.

            ════════════════════════════════════════
            YOUR TASK
            ════════════════════════════════════════
            Extract technical skills from the student's resume.
            Only extract skills that appear in the SKILLS CATALOG below.
            If a skill is mentioned using an alternative name (alias), map it to the canonical skill name.

            ════════════════════════════════════════
            SKILLS CATALOG  (canonical name → aliases → category)
            ════════════════════════════════════════
            {$skillsCatalog}

            ════════════════════════════════════════
            EXTRACTION RULES
            ════════════════════════════════════════
            1. Return JSON only — no markdown, no explanation.
            2. Only include skills that are clearly evidenced in the resume.
            3. Do NOT invent or assume skills not mentioned.
            4. If a skill is only listed by name with no context, assign initial_level between 0.5 and 1.5.
            5. If a skill is mentioned with projects, work experience, or descriptions, assign higher levels.
            6. Always use the canonical skill name (from the catalog), not the alias.
            7. initial_level: number from 0 to 5 (decimals allowed, e.g. 2.5).
            8. confidence: number from 0 to 1 reflecting how certain you are about the level estimate.
            9. evidence: short phrase copied or paraphrased directly from the resume.

            ════════════════════════════════════════
            LEVEL GUIDE
            ════════════════════════════════════════
            1 = Mentioned only, no context
            2 = Used in a simple project or course
            3 = Applied in multiple projects or internships
            4 = Advanced usage with architecture or optimization
            5 = Expert — led teams, designed systems, contributed to libraries
            PROMPT;
    }

    // ─────────────────────────────────────────────────────────────
    // بناء الـ User Prompt
    // ─────────────────────────────────────────────────────────────

    private function buildUserPrompt(string $resumeText, string $careerPath): string
    {
        return <<<PROMPT
        Career path context: {$careerPath}

        Resume text:
        {$resumeText}

        Return JSON in this exact format:
        {
        "skills": [
            {
            "skill_name": "Python",
            "evidence": "Built REST APIs using Python and Flask during internship at TechCorp",
            "initial_level": 3.0,
            "confidence": 0.85
            },
            {
            "skill_name": "SQL",
            "evidence": "mysql",
            "initial_level": 1.0,
            "confidence": 0.60
            }
        ]
        }
        PROMPT;
    }
}
