<?php

namespace Tests\Unit\Chatbot;

use App\Services\AI\AIClientInterface;
use App\Services\Chatbot\ChatbotIntentFallbackClassifier;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

class ChatbotIntentFallbackClassifierTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_accepts_only_an_allowed_intent(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn(['intent' => 'missing_skills']);

        $result = (new ChatbotIntentFallbackClassifier($ai))->classify(
            mode: 'skills_market_analysis',
            message: 'وين أكبر نقطة ضعف عندي؟',
            language: 'ar',
            availableIntents: $this->intents(),
        );

        self::assertSame('missing_skills', $result);
    }

    public function test_it_allows_the_explicit_out_of_scope_intent(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn(['intent' => 'out_of_scope']);

        $result = (new ChatbotIntentFallbackClassifier($ai))->classify(
            mode: 'skills_market_analysis',
            message: 'اكتب لي متجر Laravel',
            language: 'ar',
            availableIntents: $this->intents(),
        );

        self::assertSame('out_of_scope', $result);
    }

    public function test_it_rejects_an_invented_intent(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn(['intent' => 'invented_intent']);

        self::assertNull((new ChatbotIntentFallbackClassifier($ai))->classify(
            mode: 'skills_market_analysis',
            message: 'Question',
            language: 'en',
            availableIntents: $this->intents(),
        ));
    }

    public function test_it_fails_safely_when_the_provider_throws(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andThrow(new RuntimeException('Provider unavailable'));

        self::assertNull((new ChatbotIntentFallbackClassifier($ai))->classify(
            mode: 'skills_market_analysis',
            message: 'Question',
            language: 'en',
            availableIntents: $this->intents(),
        ));
    }

    public function test_it_does_not_call_ai_when_intent_fallback_is_disabled(): void
    {
        config(['chatbot.intent_classification.ai_fallback_enabled' => false]);

        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldNotReceive('generateJson');

        self::assertNull((new ChatbotIntentFallbackClassifier($ai))->classify(
            mode: 'skills_market_analysis',
            message: 'Question',
            language: 'en',
            availableIntents: $this->intents(),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function intents(): array
    {
        return [
            'missing_skills' => 'Show skill gaps.',
            'summary' => 'Show a general summary.',
            'out_of_scope' => 'Question is outside this mode.',
        ];
    }
}
