<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;

class ChatbotSkillsMarketQuestionResolver
{
    public const INTENT_SUMMARY = 'summary';

    public const INTENT_REGISTERED_SKILLS = 'registered_skills';

    public const INTENT_CURRENT_LEVEL = 'current_level';

    public const INTENT_CAREER_PATH = 'career_path';

    public const INTENT_MISSING_SKILLS = 'missing_skills';

    public const INTENT_MARKET_DEMAND = 'market_demand';

    public const INTENT_COMPARISON = 'comparison';

    public const INTENT_NEXT_STEP = 'next_step';

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
            mode: ChatbotConversation::MODE_SKILLS_MARKET_ANALYSIS,
            message: $message,
            language: $language,
            availableIntents: self::intentDescriptions(),
        );

        // If the provider is unavailable, keep the previous safe behavior:
        // show a general summary instead of failing the conversation.
        return $aiIntent ?? self::INTENT_SUMMARY;
    }

    public function resolveLocally(string $message): ?string
    {
        $normalized = $this->normalize($message);

        if ($this->containsAny($normalized, [
            'مقارنه',
            'قارن',
            'الفرق بين مهاراتي',
            'مقابل سوق العمل',
            'مقارنه بسوق العمل',
            'compare',
            'comparison',
            'compared to the market',
            'skills versus market',
            'skills vs market',
        ])) {
            return self::INTENT_COMPARISON;
        }

        if ($this->containsAny($normalized, [
            'ماذا اتعلم',
            'شو اتعلم',
            'بماذا ابدا',
            'باي مهاره ابدا',
            'اي مهاره ابدا',
            'ترتيب الاولويات',
            'اولويه التعلم',
            'الخطوه التاليه',
            'لماذا اقترح',
            'ليش اقترح',
            'what should i learn',
            'what to learn',
            'where should i start',
            'which skill first',
            'which skill should i start',
            'what skill should i start',
            'learning priority',
            'next step',
            'why was this skill suggested',
        ])) {
            return self::INTENT_NEXT_STEP;
        }

        if ($this->containsAny($normalized, [
            'المهارات الناقصه',
            'شو ناقصني',
            'ما ينقصني',
            'فجوات المهارات',
            'فجوه المهارات',
            'ناقصه لدي',
            'missing skills',
            'skill gaps',
            'what skills am i missing',
            'what am i missing',
        ])) {
            return self::INTENT_MISSING_SKILLS;
        }

        if ($this->containsAny($normalized, [
            'المهارات الاكثر طلبا',
            'الاكثر طلبا',
            'المهارات المطلوبه',
            'سوق العمل',
            'طلب السوق',
            'market demand',
            'most demanded skills',
            'most in demand',
            'required by the market',
            'job market',
        ])) {
            return self::INTENT_MARKET_DEMAND;
        }

        if ($this->containsAny($normalized, [
            'مستواي الحالي',
            'شو مستواي',
            'ما مستواي',
            'مستوى مهاراتي',
            'current level',
            'my level',
            'skill levels',
        ])) {
            return self::INTENT_CURRENT_LEVEL;
        }

        if ($this->containsAny($normalized, [
            'المسار المهني',
            'مساري المهني',
            'المسار المختار',
            'career path',
            'selected path',
            'my path',
        ])) {
            return self::INTENT_CAREER_PATH;
        }

        if ($this->containsAny($normalized, [
            'المهارات المسجله',
            'مهاراتي الحاليه',
            'شو مهاراتي',
            'ما مهاراتي',
            'المهارات التي امتلكها',
            'registered skills',
            'my skills',
            'skills do i have',
            'current skills',
        ])) {
            return self::INTENT_REGISTERED_SKILLS;
        }

        if ($this->containsAny($normalized, [
            'ملخص مهاراتي',
            'نظره عامه عن وضعي',
            'لخص وضعي',
            'ملخص عن السوق ومهاراتي',
            'skills summary',
            'overview of my skills',
            'general overview',
        ])) {
            return self::INTENT_SUMMARY;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function intentDescriptions(): array
    {
        return [
            self::INTENT_REGISTERED_SKILLS => 'Show the skills currently registered for the authenticated student.',
            self::INTENT_CURRENT_LEVEL => 'Show the current recorded proficiency level of the student skills.',
            self::INTENT_CAREER_PATH => 'Show the career path linked to the latest student assessment.',
            self::INTENT_MISSING_SKILLS => 'Show the student skill gaps or the main weaknesses compared with the selected path.',
            self::INTENT_MARKET_DEMAND => 'Show the most demanded skills in the labor market for the student career path.',
            self::INTENT_COMPARISON => 'Compare the student registered skills and gaps with labor-market demand.',
            self::INTENT_NEXT_STEP => 'Recommend which skill the student should learn first and explain the existing backend reason.',
            self::INTENT_SUMMARY => 'Provide a general summary of the student skills, path, gaps, and market demand.',
            self::INTENT_OUT_OF_SCOPE => 'The question is unrelated to student skills, career path, skill gaps, learning priority, or labor-market analysis.',
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
