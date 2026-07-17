<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use RuntimeException;

trait ResolvesExpertQuestionsByTopic
{
    private const EXPERT_BACKEND_CAREER_PATH = 'Backend Developer';

    /**
     * Resolve a question by stable business keys, never by a stored QuestionID.
     */
    protected function resolveExpertQuestionByTopic(
        string $skillName,
        string $topic,
    ): object {
        $careerPathId = DB::table('career_paths')
            ->where('Name', self::EXPERT_BACKEND_CAREER_PATH)
            ->value('CareerPathID');

        if (! $careerPathId) {
            throw new RuntimeException(
                'Backend Developer career path was not found.'
            );
        }

        $skillId = DB::table('skills')
            ->where('name', $skillName)
            ->value('id');

        if (! $skillId) {
            throw new RuntimeException(
                "Skill {$skillName} was not found."
            );
        }

        $question = DB::table('question_bank')
            ->where('CareerPathID', $careerPathId)
            ->where('SkillID', $skillId)
            ->where('Topic', $topic)
            ->lockForUpdate()
            ->first();

        if (! $question) {
            throw new RuntimeException(
                "Expert question was not found for skill {$skillName} and topic {$topic}."
            );
        }

        if (strtolower((string) $question->QuestionType) !== 'open_text') {
            throw new RuntimeException(
                "Expert question topic {$topic} must be open_text."
            );
        }

        return $question;
    }

    /**
     * IDs are resolved only at runtime, after the catalog has been seeded.
     */
    protected function expertQuestionIdsByTopics(
        string $skillName,
        array $topics,
    ): array {
        $ids = [];

        foreach ($topics as $topic) {
            $ids[] = (int) $this->resolveExpertQuestionByTopic(
                skillName: $skillName,
                topic: $topic,
            )->QuestionID;
        }

        return array_values(array_unique($ids));
    }
}
