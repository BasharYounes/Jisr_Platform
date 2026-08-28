<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ConversationMessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(
            'conversations.'.$this->message->conversation_id
        );
    }

    public function broadcastAs(): string
    {
        return 'conversation.message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender:id,name,email');

        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'type' => $this->message->type,
                'content' => $this->message->content,

                'sender' => [
                    'id' => $this->message->sender->id,
                    'name' => $this->message->sender->name,
                    'email' => $this->message->sender->email,
                ],

                'read_at' => $this->message->read_at,
                'is_read' => $this->message->read_at !== null,
                'created_at' => $this->message->created_at,
                'updated_at' => $this->message->updated_at,
            ],
        ];
    }
}
