<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotKnowledgeEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatbotKnowledgeMatcher
{
    public function match(string $message, string $language): ?ChatbotKnowledgeEntry
    {
        $language = $language === 'en' ? 'en' : 'ar';
        $normalizedMessage = $this->normalize($message, $language);

        if ($normalizedMessage === '') {
            return null;
        }

        $ranked = $this->platformHelpEntries()
            ->map(function (ChatbotKnowledgeEntry $entry) use (
                $normalizedMessage,
                $language,
            ): array {
                return [
                    'entry' => $entry,
                    'score' => $this->scoreEntry(
                        entry: $entry,
                        normalizedMessage: $normalizedMessage,
                        language: $language,
                    ),
                ];
            })
            ->sortByDesc('score')
            ->values();

        $best = $ranked->get(0);

        if ($best === null) {
            return null;
        }

        $minimumScore = (float) config(
            'chatbot.knowledge_matching.minimum_score',
            0.62,
        );

        if ($best['score'] < $minimumScore) {
            return null;
        }

        $second = $ranked->get(1);
        $ambiguityMargin = (float) config(
            'chatbot.knowledge_matching.ambiguity_margin',
            0.08,
        );

        if (
            $second !== null
            && ($best['score'] - $second['score']) < $ambiguityMargin
        ) {
            return null;
        }

        return $best['entry'];
    }

    private function platformHelpEntries(): Collection
    {
        return ChatbotKnowledgeEntry::query()
            ->where('category', 'platform_help')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    private function scoreEntry(
        ChatbotKnowledgeEntry $entry,
        string $normalizedMessage,
        string $language,
    ): float {
        $questionField = $language === 'ar' ? 'question_ar' : 'question_en';
        $candidates = [$entry->{$questionField}];
        $keywords = $entry->keywords[$language] ?? [];

        foreach ($keywords as $keyword) {
            if (is_string($keyword) && trim($keyword) !== '') {
                $candidates[] = $keyword;
            }
        }

        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $normalizedCandidate = $this->normalize($candidate, $language);

            if ($normalizedCandidate === '') {
                continue;
            }

            $bestScore = max(
                $bestScore,
                $this->scoreCandidate(
                    normalizedMessage: $normalizedMessage,
                    normalizedCandidate: $normalizedCandidate,
                ),
            );
        }

        return $bestScore;
    }

    private function scoreCandidate(
        string $normalizedMessage,
        string $normalizedCandidate,
    ): float {
        if ($normalizedMessage === $normalizedCandidate) {
            return 1.0;
        }

        $messageTokens = $this->tokens($normalizedMessage);
        $candidateTokens = $this->tokens($normalizedCandidate);

        if ($messageTokens === [] || $candidateTokens === []) {
            return 0.0;
        }

        $candidateTokenCount = count($candidateTokens);

        // A complete multi-word phrase inside the student's question is a strong match.
        if (
            $candidateTokenCount >= 2
            && Str::contains($normalizedMessage, $normalizedCandidate)
        ) {
            return min(0.98, 0.88 + ($candidateTokenCount * 0.02));
        }

        $intersection = array_values(array_intersect(
            $messageTokens,
            $candidateTokens,
        ));
        $intersectionCount = count($intersection);

        if ($intersectionCount === 0) {
            return 0.0;
        }

        // A single generic word must not select an answer from a long question.
        if ($candidateTokenCount === 1) {
            return count($messageTokens) === 1 ? 0.90 : 0.35;
        }

        $coverage = $intersectionCount / $candidateTokenCount;
        $precision = $intersectionCount / count($messageTokens);

        return min(0.95, ($coverage * 0.75) + ($precision * 0.25));
    }

    private function normalize(string $text, string $language): string
    {
        $text = Str::lower(trim($text));
        $text = str_replace('ـ', '', $text);

        if ($language === 'ar') {
            $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text) ?? $text;
            $text = strtr($text, [
                'أ' => 'ا',
                'إ' => 'ا',
                'آ' => 'ا',
                'ٱ' => 'ا',
                'ى' => 'ي',
                'ؤ' => 'و',
                'ئ' => 'ي',
            ]);
        }

        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return Str::squish($text);
    }

    private function tokens(string $text): array
    {
        return array_values(array_unique(array_filter(
            explode(' ', $text),
            static fn (string $token): bool => $token !== '',
        )));
    }
}
