<?php

namespace App\Interfaces;

use App\Models\ConversationParticipant;
use Carbon\CarbonInterface;

interface ConversationParticipantRepositoryInterface
{
    public function addParticipant(int $conversationId, int $userId, string $role): ConversationParticipant;

    public function exists(int $conversationId, int $userId): bool;

public function markAsRead(int $conversationId,int $userId,CarbonInterface $readAt): bool;}