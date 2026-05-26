<?php

namespace App\Http\Controllers\Api\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationMessageRequest;
use App\Http\Requests\Conversations\StoreConversationMessageRequest as ConversationsStoreConversationMessageRequest;
use App\Services\Conversations\ConversationMessageService;

use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class ConversationMessageController extends Controller
{
    use ApiResponse;
     public function __construct(
        private readonly ConversationMessageService $conversationMessageService
    ) {}   

    public function index(Request $request, int $conversationId)
    {
        $messages = $this->conversationMessageService->getMessages(
            conversationId: $conversationId,
            userId: $request->user()->id,
            perPage: (int) $request->get('per_page', 30),
        );

        return $this->success(
            data: $messages,
            message: 'Messages retrieved successfully.'
        );
    }

    public function store(ConversationsStoreConversationMessageRequest $request, int $conversationId)
    {
        $message = $this->conversationMessageService->sendMessage(
            conversationId: $conversationId,
            senderId: $request->user()->id,
            data: $request->validated(),
        );

        return $this->success(
            message: 'Message sent successfully.',
            data: $message,
            statusCode: 201
        );
    }
}