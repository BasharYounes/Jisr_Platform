<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Resources\Conversation\MessageResource;
use App\Http\Resources\Conversation\TaskConversationResource;
use App\Services\Conversations\ConversationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function index(Request $request)
    {
        $conversations = $this->conversationService->getUserTaskConversations(
            userId: $request->user()->id,
            perPage: (int) $request->get('per_page', 15),
        );

        return $this->success(
            message: 'Conversations retrieved successfully.',
            data: TaskConversationResource::collection($conversations)->resolve()
        );
    }

    public function messages(Request $request, int $conversationId)
    {
        $messages = $this->conversationService->getMessages(
            conversationId: $conversationId,
            userId: $request->user()->id,
            perPage: (int) $request->get('per_page', 30),
        );

        return $this->success(
            message: 'Messages retrieved successfully.',
            data: MessageResource::collection($messages)->resolve()
        );
    }
}