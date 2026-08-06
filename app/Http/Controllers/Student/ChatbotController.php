<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chatbot\CreateChatbotConversationRequest;
use App\Http\Requests\Chatbot\SendChatbotMessageRequest;
use App\Http\Resources\Chatbot\ChatbotConversationResource;
use App\Http\Resources\Chatbot\ChatbotMessageResource;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Services\Chatbot\ChatbotConversationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ChatbotController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ChatbotConversationService $conversationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $conversations = $this->conversationService->listConversations(
            studentId: $request->user()->id,
            limit: $request->integer('limit') ?: null,
        );

        return $this->success(
            'تم جلب محادثات المساعد بنجاح. | Chatbot conversations retrieved successfully.',
            [
                'items' => $this->resolveConversationCollection(
                    $conversations->getCollection(),
                    $request,
                ),
                'next_cursor' => $conversations->nextCursor()?->encode(),
                'has_more' => $conversations->hasMorePages(),
            ],
        );
    }

    public function store(CreateChatbotConversationRequest $request): JsonResponse
    {
        $result = $this->conversationService->createConversationWithFirstMessage(
            studentId: $request->user()->id,
            mode: $request->validated('mode'),
            message: $request->validated('message'),
            clientMessageId: $request->validated('client_message_id'),
        );

        return $this->success(
            $result['duplicated']
                ? 'تم استرجاع الرسالة المحفوظة مسبقًا. | Existing message retrieved.'
                : 'تم إنشاء المحادثة ومعالجة الرسالة بنجاح. | Conversation created and message processed successfully.',
            [
                'duplicated' => $result['duplicated'],
                'conversation' => $this->resolveConversation(
                    $result['conversation'],
                    $request,
                ),
                'user_message' => $this->resolveMessage(
                    $result['user_message'],
                    $request,
                ),
                'assistant_message' => $this->resolveNullableMessage(
                    $result['assistant_message'],
                    $request,
                ),
                'processing_status' => $result['processing_status'],
            ],
            $result['duplicated'] ? 200 : 201,
        );
    }

    public function show(Request $request, int $conversationId): JsonResponse
    {
        $conversation = $this->conversationService->getConversation(
            studentId: $request->user()->id,
            conversationId: $conversationId,
        );

        return $this->success(
            'تم جلب المحادثة بنجاح. | Conversation retrieved successfully.',
            $this->resolveConversation($conversation, $request),
        );
    }

    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $messages = $this->conversationService->listMessages(
            studentId: $request->user()->id,
            conversationId: $conversationId,
            limit: $request->integer('limit') ?: null,
        );

        $items = $messages->getCollection()->reverse()->values();

        return $this->success(
            'تم جلب رسائل المحادثة بنجاح. | Conversation messages retrieved successfully.',
            [
                'items' => $this->resolveMessageCollection($items, $request),
                'next_cursor' => $messages->nextCursor()?->encode(),
                'has_more' => $messages->hasMorePages(),
            ],
        );
    }

    public function storeMessage(
        SendChatbotMessageRequest $request,
        int $conversationId,
    ): JsonResponse {
        $result = $this->conversationService->addUserMessage(
            studentId: $request->user()->id,
            conversationId: $conversationId,
            message: $request->validated('message'),
            clientMessageId: $request->validated('client_message_id'),
        );

        return $this->success(
            $result['duplicated']
                ? 'تم استرجاع الرسالة المحفوظة مسبقًا. | Existing message retrieved.'
                : 'تمت معالجة الرسالة بنجاح. | Message processed successfully.',
            [
                'duplicated' => $result['duplicated'],
                'user_message' => $this->resolveMessage(
                    $result['user_message'],
                    $request,
                ),
                'assistant_message' => $this->resolveNullableMessage(
                    $result['assistant_message'],
                    $request,
                ),
                'processing_status' => $result['processing_status'],
            ],
            $result['duplicated'] ? 200 : 201,
        );
    }

    public function destroy(Request $request, int $conversationId): JsonResponse
    {
        $this->conversationService->deleteConversation(
            studentId: $request->user()->id,
            conversationId: $conversationId,
        );

        return $this->success(
            'تم حذف المحادثة بنجاح. | Conversation deleted successfully.',
        );
    }

    private function resolveConversation(
        ChatbotConversation $conversation,
        Request $request,
    ): array {
        return (new ChatbotConversationResource($conversation))->resolve($request);
    }

    private function resolveMessage(
        ChatbotMessage $message,
        Request $request,
    ): array {
        return (new ChatbotMessageResource($message))->resolve($request);
    }

    private function resolveNullableMessage(
        ?ChatbotMessage $message,
        Request $request,
    ): ?array {
        return $message === null
            ? null
            : $this->resolveMessage($message, $request);
    }

    private function resolveConversationCollection(
        Collection $conversations,
        Request $request,
    ): array {
        return ChatbotConversationResource::collection($conversations)
            ->resolve($request);
    }

    private function resolveMessageCollection(
        Collection $messages,
        Request $request,
    ): array {
        return ChatbotMessageResource::collection($messages)
            ->resolve($request);
    }
}
