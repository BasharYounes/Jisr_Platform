<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;

class ChatbotOpportunityQuestionResolver
{
    public const INTENT_FIND_AND_EXPLAIN = 'find_and_explain_opportunities';

    public const INTENT_OUT_OF_SCOPE = 'out_of_scope';

    public function __construct(
        private readonly ChatbotIntentFallbackClassifier $aiClassifier,
    ) {}

    public function resolve(string $message, string $language = 'ar'): string
    {
        $localIntent = $this->resolveLocally($message);

        if ($localIntent !== null) {
            return $localIntent;
        }

        $aiIntent = $this->aiClassifier->classify(
            mode: ChatbotConversation::MODE_OPPORTUNITY_MATCHING,
            message: $message,
            language: $language,
            availableIntents: self::intentDescriptions(),
        );

        return $aiIntent ?? self::INTENT_OUT_OF_SCOPE;
    }

    public function resolveLocally(string $message): ?string
    {
        $normalized = $this->normalize($message);

        if ($this->containsAny($normalized, [
            'ابحث لي عن فرصه',
            'ابحث عن فرصه',
            'فرصه مناسبه',
            'فرص مناسبه',
            'وظيفه مناسبه',
            'تدريب مناسب',
            'شو الفرص المناسبه',
            'ما الفرص المناسبه',
            'اعرض الفرص',
            'لماذا تناسبني',
            'ليش تناسبني',
            'ليش هذه الفرصه مناسبه',
            'سبب مناسبه الفرصه',
            'find a suitable opportunity',
            'find suitable opportunities',
            'search for an opportunity',
            'best opportunities for me',
            'matching opportunities',
            'why is this opportunity suitable',
            'why does this opportunity fit me',
            'explain why it matches',
        ])) {
            return self::INTENT_FIND_AND_EXPLAIN;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function intentDescriptions(): array
    {
        return [
            self::INTENT_FIND_AND_EXPLAIN => 'Find currently published opportunities suitable for the student and explain why they match the student skills.',
            self::INTENT_OUT_OF_SCOPE => 'The question is not asking to find or explain suitable opportunities.',
        ];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? $value;
        $value = strtr($value, [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ى' => 'ي',
            'ؤ' => 'و',
            'ئ' => 'ي',
            'ة' => 'ه',
        ]);
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function containsAny(string $message, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($message, $this->normalize($phrase))) {
                return true;
            }
        }

        return false;
    }
}
