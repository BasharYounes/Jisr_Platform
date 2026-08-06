<?php

namespace Tests\Unit\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Services\Chatbot\ChatbotMessageProcessor;
use App\Services\Chatbot\ChatbotOpportunityMatchingService;
use App\Services\Chatbot\ChatbotPlatformHelpService;
use App\Services\Chatbot\ChatbotSkillsMarketAnalysisService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ChatbotMessageProcessorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_routes_platform_help_to_the_platform_service(): void
    {
        $assistant = new ChatbotMessage(['status' => ChatbotMessage::STATUS_COMPLETED]);
        [$processor, $platform, $skills, $opportunities] = $this->processor();
        $conversation = new ChatbotConversation(['mode' => ChatbotConversation::MODE_PLATFORM_HELP]);
        $userMessage = new ChatbotMessage(['content' => 'What is Jisr?']);

        $platform->shouldReceive('answer')->once()->with($conversation, $userMessage)->andReturn($assistant);
        $skills->shouldNotReceive('answer');
        $opportunities->shouldNotReceive('answer');

        $result = $processor->process($conversation, $userMessage);

        self::assertSame($assistant, $result['assistant_message']);
        self::assertSame('completed', $result['processing_status']);
    }

    public function test_it_routes_skills_market_analysis_to_the_correct_service(): void
    {
        $assistant = new ChatbotMessage(['status' => ChatbotMessage::STATUS_COMPLETED]);
        [$processor, $platform, $skills, $opportunities] = $this->processor();
        $conversation = new ChatbotConversation(['mode' => ChatbotConversation::MODE_SKILLS_MARKET_ANALYSIS]);
        $userMessage = new ChatbotMessage(['content' => 'ما مهاراتي؟']);

        $platform->shouldNotReceive('answer');
        $skills->shouldReceive('answer')->once()->with($conversation, $userMessage)->andReturn($assistant);
        $opportunities->shouldNotReceive('answer');

        $result = $processor->process($conversation, $userMessage);

        self::assertSame('completed', $result['processing_status']);
    }

    public function test_it_reports_failed_when_a_mode_service_returns_a_failed_message(): void
    {
        $assistant = new ChatbotMessage(['status' => ChatbotMessage::STATUS_FAILED]);
        [$processor, $platform, $skills, $opportunities] = $this->processor();
        $conversation = new ChatbotConversation(['mode' => ChatbotConversation::MODE_OPPORTUNITY_MATCHING]);
        $userMessage = new ChatbotMessage(['content' => 'Find an opportunity']);

        $platform->shouldNotReceive('answer');
        $skills->shouldNotReceive('answer');
        $opportunities->shouldReceive('answer')->once()->with($conversation, $userMessage)->andReturn($assistant);

        $result = $processor->process($conversation, $userMessage);

        self::assertSame('failed', $result['processing_status']);
    }

    public function test_it_rejects_an_unsupported_mode_without_calling_any_service(): void
    {
        [$processor, $platform, $skills, $opportunities] = $this->processor();
        $conversation = new ChatbotConversation(['mode' => 'unsupported']);
        $userMessage = new ChatbotMessage(['content' => 'Question']);

        $platform->shouldNotReceive('answer');
        $skills->shouldNotReceive('answer');
        $opportunities->shouldNotReceive('answer');

        $result = $processor->process($conversation, $userMessage);

        self::assertNull($result['assistant_message']);
        self::assertSame('unsupported_mode', $result['processing_status']);
    }

    private function processor(): array
    {
        $platform = Mockery::mock(ChatbotPlatformHelpService::class);
        $skills = Mockery::mock(ChatbotSkillsMarketAnalysisService::class);
        $opportunities = Mockery::mock(ChatbotOpportunityMatchingService::class);

        return [
            new ChatbotMessageProcessor($platform, $skills, $opportunities),
            $platform,
            $skills,
            $opportunities,
        ];
    }
}
