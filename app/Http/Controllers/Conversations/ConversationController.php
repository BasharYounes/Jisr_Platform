<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Services\Conversations\ConversationService;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;


class ConversationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function index(Request $request)
    {
        $conversations = $this->conversationService->getUserConversations(
            userId: $request->user()->id,
            perPage: (int) $request->get('per_page', 15),
        );

        return $this->success(
            message: 'Conversations retrieved successfully.',
            data: $conversations
        );
    }

    public function show(Request $request, int $conversationId)
    {
        $conversation = $this->conversationService->getUserConversation(
            conversationId: $conversationId,
            userId: $request->user()->id,
        );

        return $this->success(
            message: 'Conversation retrieved successfully.',
            data: $conversation
        );
    }
}