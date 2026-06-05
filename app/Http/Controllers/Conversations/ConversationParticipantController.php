<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Interfaces\ConversationParticipantRepositoryInterface;
use App\Interfaces\ConversationRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Services\Conversations\ConversationService;
class ConversationParticipantController extends Controller
{
    use ApiResponse;

   

public function __construct(
    private readonly ConversationService $conversationService,
) {}

    public function markAsRead(Request $request, int $conversationId)
{
    $this->conversationService->markAsRead(
        conversationId: $conversationId,
        userId: $request->user()->id,
    );

    return $this->success(
        message: 'Conversation marked as read successfully.',
        data: null,
    );
}
}