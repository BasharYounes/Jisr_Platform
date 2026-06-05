<?php

namespace App\Services\Conversations;

use App\Interfaces\ConversationParticipantRepositoryInterface;
use App\Interfaces\ConversationRepositoryInterface;
use App\Interfaces\MessageRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use App\Models\Conversation;

class TaskAssignmentConversationService
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly ConversationParticipantRepositoryInterface $participantRepository,
        private readonly MessageRepositoryInterface $messageRepository,
    ) {}

    public function createForAssignment(CompanyTaskAssignment $assignment): Conversation
    {
        $assignment->loadMissing([
            'task.company.users',
            'student',
        ]);

        $existingConversation = $this->conversationRepository->findByConversationable(
            $assignment::class,
            $assignment->id
        );

        if ($existingConversation) {
            return $existingConversation;
        }

        $conversation = $this->conversationRepository->createForConversationable($assignment);

        $companyUserId = $assignment->task
            ->company
            ->users()
            ->firstOrFail()
            ->id;

        $this->participantRepository->addParticipant(
            conversationId: $conversation->id,
            userId: $companyUserId,
            role: 'company',
        );

        $this->participantRepository->addParticipant(
            conversationId: $conversation->id,
            userId: $assignment->student_user_id,
            role: 'student',
        );

        $this->messageRepository->createSystemMessage(
            conversationId: $conversation->id,
            content: 'تم قبولك بالمهمة ويمكنك الآن استخدام هذه المحادثة للتواصل مع الشركة أثناء التنفيذ.',
        );

        return $conversation;
    }
}