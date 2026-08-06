<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;

class ChatbotMessageProcessor
{
    public function __construct(
        private readonly ChatbotPlatformHelpService $platformHelpService,
        private readonly ChatbotSkillsMarketAnalysisService $skillsMarketAnalysisService,
        private readonly ChatbotOpportunityMatchingService $opportunityMatchingService,
    ) {}

    public function process(
        ChatbotConversation $conversation,
        ChatbotMessage $userMessage,
    ): array {
        if ($conversation->mode === ChatbotConversation::MODE_PLATFORM_HELP) {
            return [
                'assistant_message' => $this->platformHelpService->answer(
                    conversation: $conversation,
                    userMessage: $userMessage,
                ),
                'processing_status' => 'completed',
            ];
        }

        if ($conversation->mode === ChatbotConversation::MODE_SKILLS_MARKET_ANALYSIS) {
            $assistantMessage = $this->skillsMarketAnalysisService->answer(
                conversation: $conversation,
                userMessage: $userMessage,
            );

            return [
                'assistant_message' => $assistantMessage,
                'processing_status' => $assistantMessage->status === ChatbotMessage::STATUS_COMPLETED
                    ? 'completed'
                    : 'failed',
            ];
        }

        if ($conversation->mode === ChatbotConversation::MODE_OPPORTUNITY_MATCHING) {
            $assistantMessage = $this->opportunityMatchingService->answer(
                conversation: $conversation,
                userMessage: $userMessage,
            );

            return [
                'assistant_message' => $assistantMessage,
                'processing_status' => $assistantMessage->status === ChatbotMessage::STATUS_COMPLETED
                    ? 'completed'
                    : 'failed',
            ];
        }

        return [
            'assistant_message' => null,
            'processing_status' => 'unsupported_mode',
        ];
    }

    public function findExistingAssistantReply(
        ChatbotMessage $userMessage,
    ): ?ChatbotMessage {
        return ChatbotMessage::query()
            ->where('conversation_id', $userMessage->conversation_id)
            ->where('role', ChatbotMessage::ROLE_ASSISTANT)
            ->where('id', '>', $userMessage->id)
            ->orderBy('id')
            ->first();
    }
}
