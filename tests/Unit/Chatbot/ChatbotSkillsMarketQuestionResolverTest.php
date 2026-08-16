<?php

namespace Tests\Unit\Chatbot;

use App\Models\ChatbotConversation;
use App\Services\Chatbot\ChatbotIntentFallbackClassifier;
use App\Services\Chatbot\ChatbotSkillsMarketQuestionResolver;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChatbotSkillsMarketQuestionResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    #[DataProvider('questionsProvider')]
    public function test_it_resolves_approved_skills_market_questions(
        string $question,
        string $expectedIntent,
    ): void {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldNotReceive('classify');

        $resolver = new ChatbotSkillsMarketQuestionResolver($classifier);

        self::assertSame($expectedIntent, $resolver->resolve($question));
    }

    public function test_comparison_has_priority_when_the_question_also_mentions_the_market(): void
    {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldNotReceive('classify');

        $resolver = new ChatbotSkillsMarketQuestionResolver($classifier);

        self::assertSame(
            ChatbotSkillsMarketQuestionResolver::INTENT_COMPARISON,
            $resolver->resolve('قارن مهاراتي مع سوق العمل وحدد الفرق'),
        );
    }

    public function test_clear_summary_question_is_resolved_locally(): void
    {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldNotReceive('classify');

        $resolver = new ChatbotSkillsMarketQuestionResolver($classifier);

        self::assertSame(
            ChatbotSkillsMarketQuestionResolver::INTENT_SUMMARY,
            $resolver->resolve('أعطني نظرة عامة عن وضعي'),
        );
    }


    public function test_clear_local_question_does_not_call_ai(): void
    {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldNotReceive('classify');

        $resolver = new ChatbotSkillsMarketQuestionResolver($classifier);

        self::assertSame(
            ChatbotSkillsMarketQuestionResolver::INTENT_MISSING_SKILLS,
            $resolver->resolve('ما المهارات الناقصة لدي؟', 'ar'),
        );
    }

    public function test_indirect_question_uses_ai_fallback(): void
    {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldReceive('classify')
            ->once()
            ->with(
                ChatbotConversation::MODE_SKILLS_MARKET_ANALYSIS,
                'أنا هدفي ألاقي شغل Backend أسرع، وين أكبر نقطة ضعف عندي؟',
                'ar',
                ChatbotSkillsMarketQuestionResolver::intentDescriptions(),
            )
            ->andReturn(ChatbotSkillsMarketQuestionResolver::INTENT_MISSING_SKILLS);

        $resolver = new ChatbotSkillsMarketQuestionResolver($classifier);

        self::assertSame(
            ChatbotSkillsMarketQuestionResolver::INTENT_MISSING_SKILLS,
            $resolver->resolve(
                'أنا هدفي ألاقي شغل Backend أسرع، وين أكبر نقطة ضعف عندي؟',
                'ar',
            ),
        );
    }

    public function test_ai_can_reject_a_question_as_out_of_scope(): void
    {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldReceive('classify')
            ->once()
            ->andReturn(ChatbotSkillsMarketQuestionResolver::INTENT_OUT_OF_SCOPE);

        $resolver = new ChatbotSkillsMarketQuestionResolver($classifier);

        self::assertSame(
            ChatbotSkillsMarketQuestionResolver::INTENT_OUT_OF_SCOPE,
            $resolver->resolve('اكتب لي متجر Laravel كامل', 'ar'),
        );
    }

    public function test_provider_failure_keeps_the_safe_summary_fallback(): void
    {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldReceive('classify')
            ->once()
            ->andReturnNull();

        $resolver = new ChatbotSkillsMarketQuestionResolver($classifier);

        self::assertSame(
            ChatbotSkillsMarketQuestionResolver::INTENT_SUMMARY,
            $resolver->resolve('صياغة غير واضحة', 'ar'),
        );
    }

    public static function questionsProvider(): array
    {
        return [
            ['ما المهارات المسجلة لدي؟', ChatbotSkillsMarketQuestionResolver::INTENT_REGISTERED_SKILLS],
            ['شو مهاراتي الحالية', ChatbotSkillsMarketQuestionResolver::INTENT_REGISTERED_SKILLS],
            ['What skills do I have?', ChatbotSkillsMarketQuestionResolver::INTENT_REGISTERED_SKILLS],
            ['شو مستواي الحالي؟', ChatbotSkillsMarketQuestionResolver::INTENT_CURRENT_LEVEL],
            ['What are my skill levels?', ChatbotSkillsMarketQuestionResolver::INTENT_CURRENT_LEVEL],
            ['ما المسار المهني المختار؟', ChatbotSkillsMarketQuestionResolver::INTENT_CAREER_PATH],
            ['What is my career path?', ChatbotSkillsMarketQuestionResolver::INTENT_CAREER_PATH],
            ['ما المهارات الناقصة لدي؟', ChatbotSkillsMarketQuestionResolver::INTENT_MISSING_SKILLS],
            ['شو ناقصني ضمن مساري', ChatbotSkillsMarketQuestionResolver::INTENT_MISSING_SKILLS],
            ['What skills am I missing?', ChatbotSkillsMarketQuestionResolver::INTENT_MISSING_SKILLS],
            ['ما المهارات الأكثر طلبًا في سوق العمل؟', ChatbotSkillsMarketQuestionResolver::INTENT_MARKET_DEMAND],
            ['What are the most demanded skills?', ChatbotSkillsMarketQuestionResolver::INTENT_MARKET_DEMAND],
            ['قارن مهاراتي مع سوق العمل', ChatbotSkillsMarketQuestionResolver::INTENT_COMPARISON],
            ['Compare my skills to the job market', ChatbotSkillsMarketQuestionResolver::INTENT_COMPARISON],
            ['بأي مهارة أبدأ؟', ChatbotSkillsMarketQuestionResolver::INTENT_NEXT_STEP],
            ['ليش اقترح النظام هالمهارة؟', ChatbotSkillsMarketQuestionResolver::INTENT_NEXT_STEP],
            ['What should I learn next?', ChatbotSkillsMarketQuestionResolver::INTENT_NEXT_STEP],
            ['Which skill should I start with and why?', ChatbotSkillsMarketQuestionResolver::INTENT_NEXT_STEP],
        ];
    }
}
