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
        $userPrompt = $this->buildUserPrompt($resumeText, $careerPath);

        return $this->aiClient->generateJson($systemPrompt, $userPrompt);
    }

    /**
     * Builds the canonical skills catalog that is provided to the model.
     */
    private function buildSkillsCatalog(): string
    {
        $skills = Skill::with('aliases')->get();

        if ($skills->isEmpty()) {
            return '- Python, Flask, SQL, Git';
        }

        $lines = $skills->map(function (Skill $skill) {
            $aliasesText = $skill->aliases->isNotEmpty()
                ? '  [also known as: '.$skill->aliases->pluck('Alias')->implode(', ').']'
                : '';

            return "  - {$skill->name}{$aliasesText}  (category: {$skill->category})";
        });

        return $lines->implode("\n");
    }

    /**
     * Prompt aligned with the reviewed skill-extraction evaluation prompt.
     *
     * The strict extraction rules come from the evaluated prompt, while the
     * level guide is retained because the production flow persists
     * initial_level and confidence values.
     */
    private function buildSystemPrompt(string $skillsCatalog): string
    {
        return <<<PROMPT
            You are a precise CV skill extraction engine for a talent platform.

            Return valid JSON only. No markdown. No code fences. No explanation.

            ════════════════════════════════════════
            YOUR TASK
            ════════════════════════════════════════
            Extract ONLY the technical skills that are explicitly and clearly written
            in the resume text below. Match them to the canonical names in the SKILLS CATALOG.

            ════════════════════════════════════════
            SKILLS CATALOG  (canonical name → aliases → category)
            ════════════════════════════════════════
            {$skillsCatalog}

            ════════════════════════════════════════
            STRICT RULES — follow every rule exactly
            ════════════════════════════════════════
            //
            RULE 0 — ASSESSMENT SKILL ALLOWLIST — HIGHEST PRIORITY:
            You may extract and return ONLY these four canonical skills:

            - Python
            - Flask
            - SQL
            - Git

            These are the ONLY skills currently supported by the assessment system.

            The SKILLS CATALOG may contain many other skills, but every skill other than
            Python, Flask, SQL, and Git is OUT OF SCOPE for this CV analysis and MUST
            be ignored completely.

            Even if another technical skill is explicitly written in the resume,
            do NOT return it.

            Never infer or substitute another skill.

            Return only the allowed skills that are actually and explicitly evidenced
            in the resume.

            The output may therefore contain between 0 and 4 skills maximum.

            If none of Python, Flask, SQL, or Git are explicitly found, return:
            {"skills": []}
            //
            RULE 1 — CATALOG ONLY:
              Extract ONLY skills present in the SKILLS CATALOG above.
              If a skill is not in the catalog, ignore it completely.

            RULE 2 — EXPLICIT EVIDENCE REQUIRED:
              A skill must be directly and explicitly written in the resume.
              Do NOT infer, assume, or guess skills from job titles, career-path context,
              or surrounding text unless the skill itself is explicitly evidenced.
              Example: "Software Engineer" does NOT imply Python or SQL unless written.

            RULE 3 — 90% CONFIDENCE THRESHOLD:
              If you are less than 90% sure a skill is clearly stated, do NOT include it.
              It is better to miss a skill than to invent one.

            RULE 4 — NO DUPLICATES:
              Each canonical skill name must appear ONCE in the output, maximum.
              If "mysql" and "postgresql" both appear, return "SQL" only once.

            RULE 5 — CANONICAL NAME ONLY:
            Always return the canonical name from the catalog, never the alias.
            Examples:
            "python3" → "Python"
            "flask framework" → "Flask"
            "mysql" → "SQL"
            "postgresql" → "SQL"
            "github" → "Git"
            "gitlab" → "Git"

            RULE 6 — EVIDENCE:
              evidence must be a short exact phrase or a very close paraphrase from the
              resume that directly proves the returned skill.

            RULE 7 — LEVEL AND CONFIDENCE:
              initial_level must be a number from 0 to 5.
              confidence must be a number from 0 to 1.
              confidence reflects certainty about the returned assessment.

            ════════════════════════════════════════
            LEVEL GUIDE
            ════════════════════════════════════════
            1 = Mentioned only, no context
            2 = Used in a simple project or course
            3 = Applied in multiple projects or internships
            4 = Advanced usage with architecture or optimization
            5 = Expert — led teams, designed systems, contributed to libraries

            ════════════════════════════════════════
            OUTPUT FORMAT
            ════════════════════════════════════════
            {
              "skills": [
                {
                  "skill_name": "Python",
                  "evidence": "exact phrase from resume proving this skill",
                  "initial_level": 2.5,
                  "confidence": 0.92
                }
              ]
            }

            If no catalog skills are found, return: {"skills": []}
            PROMPT;
    }

    private function buildUserPrompt(string $resumeText, string $careerPath): string
    {
        return <<<PROMPT
        Career path context: {$careerPath}

        Important: use the career path only as context. Never infer a skill from it.

        Extract skills from this resume following ALL rules strictly:

        {$resumeText}
        PROMPT;
    }
}
