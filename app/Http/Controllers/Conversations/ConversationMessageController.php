<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\SendMessageRequest;
use App\Http\Resources\Conversation\MessageResource;
use App\Services\Conversations\ConversationMessageService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ConversationMessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ConversationMessageService $conversationMessageService,
    ) {}

    /**
     * Get all messages for a conversation.
     */
    public function index(Request $request, int $conversationId)
    {
        $messages = $this->conversationMessageService->getMessages(
            conversationId: $conversationId,
            userId: $request->user()->id,
            perPage: (int) $request->get('per_page', 30),
        );

        return $this->success(
            message: 'Messages retrieved successfully.',
            data: [
                'items' => MessageResource::collection($messages)->resolve(),

                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ],
            ]
        );
    }

    /**
     * Send a new message to a conversation.
     */
    public function store(
        SendMessageRequest $request,
        int $conversationId
    ) {
        $message = $this->conversationMessageService->sendMessage(
            conversationId: $conversationId,
            senderId: $request->user()->id,
            data: $request->validated(),
        );

        $message->load('sender:id,name,email,profile_picture_url');

        return $this->success(
            message: 'Message sent successfully.',
            data: new MessageResource($message)
        );
    }
}