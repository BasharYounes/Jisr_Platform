<?php

namespace Tests\Unit\Chatbot;

use App\Models\ChatbotConversation;
use App\Services\Chatbot\ChatbotIntentFallbackClassifier;
use App\Services\Chatbot\ChatbotOpportunityQuestionResolver;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ChatbotOpportunityQuestionResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_clear_local_question_does_not_call_ai(): void
    {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldNotReceive('classify');

        $resolver = new ChatbotOpportunityQuestionResolver($classifier);

        self::assertSame(
            ChatbotOpportunityQuestionResolver::INTENT_FIND_AND_EXPLAIN,
            $resolver->resolve('ابحث لي عن فرصة مناسبة واشرح السبب', 'ar'),
        );
    }

    public function test_indirect_question_uses_ai_fallback(): void
    {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldReceive('classify')
            ->once()
            ->with(
                ChatbotConversation::MODE_OPPORTUNITY_MATCHING,
                'شو في شي بيناسب خبرتي الحالية؟',
                'ar',
                ChatbotOpportunityQuestionResolver::intentDescriptions(),
            )
            ->andReturn(ChatbotOpportunityQuestionResolver::INTENT_FIND_AND_EXPLAIN);

        $resolver = new ChatbotOpportunityQuestionResolver($classifier);

        self::assertSame(
            ChatbotOpportunityQuestionResolver::INTENT_FIND_AND_EXPLAIN,
            $resolver->resolve('شو في شي بيناسب خبرتي الحالية؟', 'ar'),
        );
    }

    public function test_out_of_scope_is_preserved(): void
    {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldReceive('classify')
            ->once()
            ->andReturn(ChatbotOpportunityQuestionResolver::INTENT_OUT_OF_SCOPE);

        $resolver = new ChatbotOpportunityQuestionResolver($classifier);

        self::assertSame(
            ChatbotOpportunityQuestionResolver::INTENT_OUT_OF_SCOPE,
            $resolver->resolve('ما المهارات المسجلة عندي؟', 'ar'),
        );
    }

    public function test_provider_failure_defaults_to_out_of_scope_instead_of_running_matching(): void
    {
        $classifier = Mockery::mock(ChatbotIntentFallbackClassifier::class);
        $classifier->shouldReceive('classify')
            ->once()
            ->andReturnNull();

        $resolver = new ChatbotOpportunityQuestionResolver($classifier);

        self::assertSame(
            ChatbotOpportunityQuestionResolver::INTENT_OUT_OF_SCOPE,
            $resolver->resolve('مرحبا', 'ar'),
        );
    }
}
