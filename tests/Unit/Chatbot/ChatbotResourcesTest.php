<?php

namespace Tests\Unit\Chatbot;

use App\Http\Resources\Chatbot\ChatbotConversationResource;
use App\Http\Resources\Chatbot\ChatbotMessageResource;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

class ChatbotResourcesTest extends TestCase
{
    public function test_conversation_resource_exposes_only_the_public_contract(): void
    {
        $now = CarbonImmutable::parse('2026-08-06 12:00:00');
        $conversation = new ChatbotConversation();
        $conversation->forceFill([
            'id' => 9,
            'student_id' => 2,
            'mode' => ChatbotConversation::MODE_PLATFORM_HELP,
            'title' => 'Question title',
            'last_message_at' => $now,
            'created_at' => $now,
            'deleted_at' => null,
        ]);
        $conversation->setRelation('latestMessage', new ChatbotMessage([
            'content' => str_repeat('A', 100),
        ]));

        $data = (new ChatbotConversationResource($conversation))
            ->resolve(Request::create('/', 'GET'));

        self::assertSame([
            'id',
            'mode',
            'title',
            'last_message_preview',
            'last_message_at',
            'created_at',
        ], array_keys($data));
        self::assertArrayNotHasKey('student_id', $data);
        self::assertArrayNotHasKey('deleted_at', $data);
        self::assertNotNull($data['last_message_preview']);
    }

    public function test_message_resource_hides_internal_delivery_fields(): void
    {
        $message = new ChatbotMessage();
        $message->forceFill([
            'id' => 4,
            'conversation_id' => 3,
            'client_message_id' => '11111111-1111-4111-8111-111111111111',
            'role' => ChatbotMessage::ROLE_ASSISTANT,
            'content' => 'Answer',
            'language' => 'en',
            'status' => ChatbotMessage::STATUS_COMPLETED,
            'actions' => null,
            'error_code' => null,
            'created_at' => CarbonImmutable::parse('2026-08-06 12:00:00'),
        ]);

        $data = (new ChatbotMessageResource($message))
            ->resolve(Request::create('/', 'GET'));

        self::assertSame([
            'id',
            'role',
            'content',
            'language',
            'status',
            'actions',
            'created_at',
        ], array_keys($data));
        self::assertSame([], $data['actions']);
        self::assertArrayNotHasKey('conversation_id', $data);
        self::assertArrayNotHasKey('client_message_id', $data);
        self::assertArrayNotHasKey('error_code', $data);
    }
}
