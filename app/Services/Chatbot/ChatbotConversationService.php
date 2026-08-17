<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatbotConversationService
{
    public function __construct(
        private readonly ChatbotMessageProcessor $messageProcessor,
    ) {}

    public function listConversations(int $studentId, ?int $limit = null): CursorPaginator
    {
        $perPage = $this->normalizeLimit(
            $limit,
            config('chatbot.conversations_per_page', 20),
            50,
        );

        return ChatbotConversation::query()
            ->with('latestMessage')
            ->where('student_id', $studentId)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function createConversationWithFirstMessage(
        int $studentId,
        string $mode,
        string $message,
        string $clientMessageId,
    ): array {
        $existingMessage = ChatbotMessage::query()
            ->with('conversation.latestMessage')
            ->where('client_message_id', $clientMessageId)
            ->where('role', ChatbotMessage::ROLE_USER)
            ->whereHas(
                'conversation',
                fn ($query) => $query->where('student_id', $studentId)
            )
            ->first();

        if ($existingMessage !== null) {
            return $this->duplicateResult($existingMessage, true);
        }

        $created = DB::transaction(function () use (
            $studentId,
            $mode,
            $message,
            $clientMessageId,
        ): array {
            $now = now();

            $conversation = ChatbotConversation::query()->create([
                'student_id' => $studentId,
                'mode' => $mode,
                'title' => $this->makeTitle($message),
                'last_message_at' => $now,
            ]);

            $userMessage = $conversation->messages()->create([
                'client_message_id' => $clientMessageId,
                'role' => ChatbotMessage::ROLE_USER,
                'content' => trim($message),
                'language' => $this->detectLanguage($message),
                'status' => ChatbotMessage::STATUS_PENDING,
                'actions' => null,
                'error_code' => null,
            ]);

            return [
                'conversation' => $conversation,
                'user_message' => $userMessage,
            ];
        });

        $processing = $this->messageProcessor->process(
            conversation: $created['conversation'],
            userMessage: $created['user_message'],
        );

        $created['conversation']->load('latestMessage');

        return [
            ...$created,
            ...$processing,
            'duplicated' => false,
        ];
    }

    public function getConversation(int $studentId, int $conversationId): ChatbotConversation
    {
        return $this->findOwnedConversation($studentId, $conversationId)
            ->load('latestMessage');
    }

    public function listMessages(
        int $studentId,
        int $conversationId,
        ?int $limit = null,
    ): CursorPaginator {
        $conversation = $this->findOwnedConversation($studentId, $conversationId);

        $perPage = $this->normalizeLimit(
            $limit,
            config('chatbot.messages_per_page', 30),
            100,
        );

        return $conversation->messages()
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function addUserMessage(
        int $studentId,
        int $conversationId,
        string $message,
        string $clientMessageId,
    ): array {
        $conversation = $this->findOwnedConversation($studentId, $conversationId);

        $existingMessage = $conversation->messages()
            ->where('client_message_id', $clientMessageId)
            ->where('role', ChatbotMessage::ROLE_USER)
            ->first();

        if ($existingMessage !== null) {
            return $this->duplicateResult($existingMessage, false);
        }

        $userMessage = DB::transaction(function () use (
            $conversation,
            $message,
            $clientMessageId,
        ): ChatbotMessage {
            $userMessage = $conversation->messages()->create([
                'client_message_id' => $clientMessageId,
                'role' => ChatbotMessage::ROLE_USER,
                'content' => trim($message),
                'language' => $this->detectLanguage($message),
                'status' => ChatbotMessage::STATUS_PENDING,
                'actions' => null,
                'error_code' => null,
            ]);

            $conversation->update([
                'last_message_at' => $userMessage->created_at,
            ]);

            return $userMessage;
        });

        $processing = $this->messageProcessor->process(
            conversation: $conversation,
            userMessage: $userMessage,
        );

        return [
            'user_message' => $userMessage->fresh(),
            ...$processing,
            'duplicated' => false,
        ];
    }

    public function deleteConversation(int $studentId, int $conversationId): void
    {
        $conversation = $this->findOwnedConversation($studentId, $conversationId);
        $conversation->delete();
    }

    private function duplicateResult(
        ChatbotMessage $existingMessage,
        bool $includeConversation,
    ): array {
        $assistantMessage = $this->messageProcessor
            ->findExistingAssistantReply($existingMessage);

        $result = [
            'user_message' => $existingMessage,
            'assistant_message' => $assistantMessage,
            'processing_status' => $assistantMessage !== null
                ? 'completed'
                : 'waiting_for_mode_implementation',
            'duplicated' => true,
        ];

        if ($includeConversation) {
            $result['conversation'] = $existingMessage->conversation;
        }

        return $result;
    }

    private function findOwnedConversation(
        int $studentId,
        int $conversationId,
    ): ChatbotConversation {
        $conversation = ChatbotConversation::query()
            ->where('student_id', $studentId)
            ->whereKey($conversationId)
            ->first();

        if ($conversation === null) {
            throw (new ModelNotFoundException)->setModel(
                ChatbotConversation::class,
                [$conversationId],
            );
        }

        return $conversation;
    }

    private function makeTitle(string $message): string
    {
        return Str::limit(Str::squish($message), 60, '…');
    }

    private function detectLanguage(string $message): string
    {
        return preg_match('/\p{Arabic}/u', $message) === 1 ? 'ar' : 'en';
    }

    private function normalizeLimit(?int $requested, int $default, int $maximum): int
    {
        if ($requested === null) {
            return $default;
        }

        return max(1, min($requested, $maximum));
    }
}
