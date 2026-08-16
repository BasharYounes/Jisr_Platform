<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotKnowledgeEntry;
use App\Models\ChatbotMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChatbotApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        config([
            'chatbot.knowledge_matching.ai_fallback_enabled' => false,
            'chatbot.knowledge_matching.minimum_score' => 0.62,
            'chatbot.knowledge_matching.ambiguity_margin' => 0.08,
        ]);

        ChatbotKnowledgeEntry::query()->updateOrCreate(
            ['key' => 'platform_overview'],
            [
                'category' => 'platform_help',
                'question_ar' => 'ما هي منصة جسر؟',
                'question_en' => 'What is Jisr?',
                'answer_ar' => 'جسر منصة تربط الطالب بسوق العمل.',
                'answer_en' => 'Jisr connects students with the labor market.',
                'keywords' => [
                    'ar' => ['ما هي منصة جسر'],
                    'en' => ['what is jisr'],
                ],
                'action' => null,
                'is_active' => true,
            ],
        );
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_create_a_conversation(): void
    {
        $this->postJson('/api/student/chatbot/conversations', $this->payload())
            ->assertUnauthorized();
    }

    public function test_invalid_mode_is_rejected(): void
    {
        $this->authenticateStudent();

        $this->postJson('/api/student/chatbot/conversations', [
            ...$this->payload(),
            'mode' => 'student_data',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mode');
    }

    public function test_platform_help_creates_a_completed_conversation_with_a_clean_contract(): void
    {
        $student = $this->authenticateStudent();
        $payload = $this->payload();

        $response = $this->postJson('/api/student/chatbot/conversations', $payload)
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.duplicated', false)
            ->assertJsonPath('data.conversation.mode', ChatbotConversation::MODE_PLATFORM_HELP)
            ->assertJsonPath('data.user_message.status', ChatbotMessage::STATUS_COMPLETED)
            ->assertJsonPath('data.assistant_message.status', ChatbotMessage::STATUS_COMPLETED)
            ->assertJsonPath('data.assistant_message.language', 'en')
            ->assertJsonPath('data.processing_status', 'completed');

        $conversation = $response->json('data.conversation');
        $assistant = $response->json('data.assistant_message');

        self::assertSame([
            'id',
            'mode',
            'title',
            'last_message_preview',
            'last_message_at',
            'created_at',
        ], array_keys($conversation));
        self::assertArrayNotHasKey('student_id', $conversation);
        self::assertArrayNotHasKey('deleted_at', $conversation);
        self::assertArrayNotHasKey('conversation_id', $assistant);
        self::assertArrayNotHasKey('client_message_id', $assistant);
        self::assertArrayNotHasKey('error_code', $assistant);

        $this->assertDatabaseHas('chatbot_conversations', [
            'id' => $conversation['id'],
            'student_id' => $student->id,
            'mode' => ChatbotConversation::MODE_PLATFORM_HELP,
        ]);
        $this->assertDatabaseHas('chatbot_messages', [
            'conversation_id' => $conversation['id'],
            'client_message_id' => $payload['client_message_id'],
            'role' => ChatbotMessage::ROLE_USER,
            'status' => ChatbotMessage::STATUS_COMPLETED,
        ]);
    }

    public function test_repeated_client_message_id_returns_the_existing_result_without_duplication(): void
    {
        $student = $this->authenticateStudent();
        $payload = $this->payload();

        $first = $this->postJson('/api/student/chatbot/conversations', $payload)
            ->assertCreated();

        $second = $this->postJson('/api/student/chatbot/conversations', $payload)
            ->assertOk()
            ->assertJsonPath('data.duplicated', true)
            ->assertJsonPath('data.processing_status', 'completed');

        self::assertSame(
            $first->json('data.conversation.id'),
            $second->json('data.conversation.id'),
        );
        self::assertSame(
            $first->json('data.user_message.id'),
            $second->json('data.user_message.id'),
        );
        self::assertSame(
            $first->json('data.assistant_message.id'),
            $second->json('data.assistant_message.id'),
        );

        $conversationId = (int) $first->json('data.conversation.id');

        self::assertSame(
            1,
            ChatbotConversation::query()->where('student_id', $student->id)->count(),
        );
        self::assertSame(
            2,
            ChatbotMessage::query()->where('conversation_id', $conversationId)->count(),
        );
    }

    public function test_student_cannot_read_another_students_conversation(): void
    {
        $owner = $this->authenticateStudent();
        $response = $this->postJson('/api/student/chatbot/conversations', $this->payload())
            ->assertCreated();
        $conversationId = (int) $response->json('data.conversation.id');

        $otherStudent = $this->student();
        self::assertNotSame($owner->id, $otherStudent->id);
        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/student/chatbot/conversations/{$conversationId}")
            ->assertNotFound();
    }

    public function test_student_can_add_a_message_and_soft_delete_the_conversation(): void
    {
        $this->authenticateStudent();
        $created = $this->postJson('/api/student/chatbot/conversations', $this->payload())
            ->assertCreated();
        $conversationId = (int) $created->json('data.conversation.id');

        $this->postJson("/api/student/chatbot/conversations/{$conversationId}/messages", [
            'message' => 'What is Jisr?',
            'client_message_id' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.duplicated', false)
            ->assertJsonPath('data.processing_status', 'completed');

        self::assertSame(
            4,
            ChatbotMessage::query()->where('conversation_id', $conversationId)->count(),
        );

        $this->deleteJson("/api/student/chatbot/conversations/{$conversationId}")
            ->assertOk();

        $this->assertSoftDeleted('chatbot_conversations', ['id' => $conversationId]);
    }

    private function authenticateStudent(): User
    {
        $student = $this->student();
        Sanctum::actingAs($student);

        return $student;
    }

    private function student(): User
    {
        $role = Role::findOrCreate('student', 'web');
        $student = User::factory()->create();
        $student->assignRole($role);

        return $student;
    }

    private function payload(): array
    {
        return [
            'mode' => ChatbotConversation::MODE_PLATFORM_HELP,
            'message' => 'What is Jisr?',
            'client_message_id' => (string) Str::uuid(),
        ];
    }
}
