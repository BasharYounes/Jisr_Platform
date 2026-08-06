<?php

namespace Tests\Unit\Chatbot;

use App\Models\ChatbotConversation;
use Tests\TestCase;

class ChatbotConversationModesTest extends TestCase
{
    public function test_it_exposes_only_the_three_approved_modes(): void
    {
        self::assertSame([
            ChatbotConversation::MODE_PLATFORM_HELP,
            ChatbotConversation::MODE_SKILLS_MARKET_ANALYSIS,
            ChatbotConversation::MODE_OPPORTUNITY_MATCHING,
        ], ChatbotConversation::allowedModes());
    }

    public function test_configured_modes_match_the_model_contract(): void
    {
        self::assertSame(
            ChatbotConversation::allowedModes(),
            config('chatbot.modes'),
        );
    }
}
