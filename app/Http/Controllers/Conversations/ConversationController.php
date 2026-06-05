<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Resources\Conversation\MessageResource;
use App\Http\Resources\Conversation\TaskConversationResource;
use App\Services\Conversations\ConversationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Resources\Conversation\ConversationResource;


class ConversationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

  public function index(Request $request)
{
    $conversations = $this->conversationService->getUserOpenConversations(
        userId: $request->user()->id,
        perPage: (int) $request->get('per_page', 15),
    );

    return $this->success(
        message: 'Conversations retrieved successfully.',
        data: [
            'items' => ConversationResource::collection($conversations)->resolve(),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]
    );
}
   public function taskConversations(Request $request)
{
    $conversations = $this->conversationService
        ->getUserTaskConversations(
            userId: $request->user()->id,
            perPage: (int) $request->get('per_page', 15),
        );

    return $this->success(
        message: 'Task conversations retrieved successfully.',
        data: [
            'items' => TaskConversationResource::collection(
                $conversations
            )->resolve(),

            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]
    );
}

public function closedConversations(Request $request)
{
    $conversations = $this->conversationService->getUserClosedConversations(
        userId: $request->user()->id,
        perPage: (int) $request->get('per_page', 15),
    );

    return $this->success(
        message: 'Closed conversations retrieved successfully.',
        data: [
            'items' => ConversationResource::collection($conversations)->resolve(),

            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]
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