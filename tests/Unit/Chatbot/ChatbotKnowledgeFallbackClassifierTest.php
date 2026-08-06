<?php

namespace Tests\Unit\Chatbot;

use App\Models\ChatbotKnowledgeEntry;
use App\Services\AI\AIClientInterface;
use App\Services\Chatbot\ChatbotKnowledgeFallbackClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

class ChatbotKnowledgeFallbackClassifierTest extends TestCase
{
    use RefreshDatabase;
    use MockeryPHPUnitIntegration;

    public function test_it_accepts_only_an_active_known_key_returned_by_the_ai_client(): void
    {
        $entry = $this->entry();
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn(['knowledge_key' => $entry->key]);

        $result = (new ChatbotKnowledgeFallbackClassifier($ai))->classify(
            'صياغة غير مباشرة لسؤال المنصة',
            'ar',
        );

        self::assertNotNull($result);
        self::assertSame($entry->id, $result->id);
    }

    public function test_it_rejects_out_of_scope(): void
    {
        $this->entry();
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn(['knowledge_key' => 'out_of_scope']);

        self::assertNull((new ChatbotKnowledgeFallbackClassifier($ai))->classify(
            'Build a complete Laravel store',
            'en',
        ));
    }

    public function test_it_rejects_a_hallucinated_key(): void
    {
        $this->entry();
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn(['knowledge_key' => 'invented_key']);

        self::assertNull((new ChatbotKnowledgeFallbackClassifier($ai))->classify(
            'Question',
            'en',
        ));
    }

    public function test_it_fails_safely_when_the_provider_throws(): void
    {
        $this->entry();
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andThrow(new RuntimeException('Provider unavailable'));

        self::assertNull((new ChatbotKnowledgeFallbackClassifier($ai))->classify(
            'Question',
            'en',
        ));
    }

    public function test_it_does_not_call_ai_when_the_fallback_is_disabled(): void
    {
        config(['chatbot.knowledge_matching.ai_fallback_enabled' => false]);

        $this->entry();
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldNotReceive('generateJson');

        self::assertNull((new ChatbotKnowledgeFallbackClassifier($ai))->classify(
            'Question',
            'en',
        ));
    }

    private function entry(): ChatbotKnowledgeEntry
    {
        $token = Str::lower(Str::random(10));

        return ChatbotKnowledgeEntry::query()->create([
            'key' => 'fallback_'.$token,
            'category' => 'platform_help',
            'question_ar' => 'سؤال احتياطي '.$token,
            'question_en' => 'Fallback question '.$token,
            'answer_ar' => 'إجابة.',
            'answer_en' => 'Answer.',
            'keywords' => ['ar' => [], 'en' => []],
            'action' => null,
            'is_active' => true,
        ]);
    }
}
