<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Interfaces\ConversationParticipantRepositoryInterface;
use App\Interfaces\ConversationRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ConversationParticipantController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly ConversationParticipantRepositoryInterface $participantRepository
    ) {}

    public function markAsRead(Request $request, int $conversationId)
    {
        $this->conversationRepository->findUserConversationOrFail(
            conversationId: $conversationId,
            userId: $request->user()->id
        );

        $this->participantRepository->markAsRead(
            conversationId: $conversationId,
            userId: $request->user()->id
        );

        return $this->success(
            message: 'Conversation marked as read successfully.',
            data: null
        );
    }
}