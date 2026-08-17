<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotKnowledgeEntry;
use App\Models\ChatbotMessage;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Services\AI\AIClientInterface;
use App\Services\Chatbot\ChatbotSkillsMarketDataService;
use App\Services\Opportunities\OpportunityRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChatbotEndToEndTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        config([
            'chatbot.knowledge_matching.ai_fallback_enabled' => false,
            'chatbot.intent_classification.ai_fallback_enabled' => false,
            'chatbot.response_formatter.enabled' => false,
            'chatbot.opportunity_matching.result_limit' => 3,
        ]);

        $this->seedPlatformKnowledge();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    public function test_platform_help_full_http_lifecycle(): void
    {
        $student = $this->authenticateStudent();
        $firstClientMessageId = (string) Str::uuid();

        $created = $this->postJson('/api/student/chatbot/conversations', [
            'mode' => ChatbotConversation::MODE_PLATFORM_HELP,
            'message' => 'What is Jisr?',
            'client_message_id' => $firstClientMessageId,
        ])
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.duplicated', false)
            ->assertJsonPath('data.conversation.mode', ChatbotConversation::MODE_PLATFORM_HELP)
            ->assertJsonPath('data.user_message.status', ChatbotMessage::STATUS_COMPLETED)
            ->assertJsonPath('data.assistant_message.status', ChatbotMessage::STATUS_COMPLETED)
            ->assertJsonPath('data.processing_status', 'completed');

        $conversationId = (int) $created->json('data.conversation.id');
        $followUpClientMessageId = (string) Str::uuid();

        $followUp = $this->postJson(
            "/api/student/chatbot/conversations/{$conversationId}/messages",
            [
                'message' => 'Where can I see my assessment result?',
                'client_message_id' => $followUpClientMessageId,
            ],
        )
            ->assertCreated()
            ->assertJsonPath('data.duplicated', false)
            ->assertJsonPath('data.processing_status', 'completed')
            ->assertJsonPath('data.assistant_message.language', 'en');

        self::assertStringContainsString(
            'assessment result',
            Str::lower((string) $followUp->json('data.assistant_message.content')),
        );

        $duplicate = $this->postJson(
            "/api/student/chatbot/conversations/{$conversationId}/messages",
            [
                'message' => 'Where can I see my assessment result?',
                'client_message_id' => $followUpClientMessageId,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.duplicated', true)
            ->assertJsonPath('data.processing_status', 'completed');

        self::assertSame(
            $followUp->json('data.user_message.id'),
            $duplicate->json('data.user_message.id'),
        );
        self::assertSame(
            $followUp->json('data.assistant_message.id'),
            $duplicate->json('data.assistant_message.id'),
        );

        $this->getJson('/api/student/chatbot/conversations?limit=10')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $conversationId)
            ->assertJsonPath('data.items.0.mode', ChatbotConversation::MODE_PLATFORM_HELP);

        $shown = $this->getJson("/api/student/chatbot/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('data.id', $conversationId);

        self::assertArrayNotHasKey('student_id', $shown->json('data'));
        self::assertArrayNotHasKey('deleted_at', $shown->json('data'));

        $messages = $this->getJson(
            "/api/student/chatbot/conversations/{$conversationId}/messages?limit=10",
        )
            ->assertOk()
            ->assertJsonCount(4, 'data.items')
            ->assertJsonPath('data.has_more', false);

        self::assertSame(
            ['user', 'assistant', 'user', 'assistant'],
            collect($messages->json('data.items'))->pluck('role')->all(),
        );

        self::assertSame(
            4,
            ChatbotMessage::query()
                ->where('conversation_id', $conversationId)
                ->count(),
        );

        $otherStudent = $this->student();
        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/student/chatbot/conversations/{$conversationId}")
            ->assertNotFound();

        Sanctum::actingAs($student);

        $this->deleteJson("/api/student/chatbot/conversations/{$conversationId}")
            ->assertOk();

        $this->assertSoftDeleted('chatbot_conversations', [
            'id' => $conversationId,
            'student_id' => $student->id,
        ]);

        $this->getJson("/api/student/chatbot/conversations/{$conversationId}")
            ->assertNotFound();
    }

    public function test_conversation_cursor_pagination_contract(): void
    {
        $this->authenticateStudent();

        foreach (range(1, 3) as $index) {
            $this->postJson('/api/student/chatbot/conversations', [
                'mode' => ChatbotConversation::MODE_PLATFORM_HELP,
                'message' => 'What is Jisr?',
                'client_message_id' => (string) Str::uuid(),
            ])->assertCreated();

        }

        $firstPage = $this->getJson('/api/student/chatbot/conversations?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.has_more', true);

        $cursor = $firstPage->json('data.next_cursor');
        self::assertIsString($cursor);
        self::assertNotSame('', $cursor);

        $secondPage = $this->getJson(
            '/api/student/chatbot/conversations?'.http_build_query([
                'limit' => 2,
                'cursor' => $cursor,
            ]),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.has_more', false);

        $firstIds = collect($firstPage->json('data.items'))->pluck('id');
        $secondIds = collect($secondPage->json('data.items'))->pluck('id');

        self::assertCount(0, $firstIds->intersect($secondIds));
    }

    public function test_skills_market_analysis_full_http_flow_with_ai_intent_and_formatting(): void
    {
        $student = $this->authenticateStudent();

        config([
            'chatbot.intent_classification.ai_fallback_enabled' => true,
            'chatbot.response_formatter.enabled' => true,
        ]);

        $dataService = Mockery::mock(ChatbotSkillsMarketDataService::class);
        $dataService->shouldReceive('build')
            ->once()
            ->with($student->id)
            ->andReturn($this->skillsMarketFacts());
        $this->app->instance(ChatbotSkillsMarketDataService::class, $dataService);

        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->twice()
            ->andReturnValues([
                ['intent' => 'missing_skills'],
                [
                    'content' => 'أكبر فجوة لديك هي Laravel: مستواك 0 بينما المستوى المطلوب 2.5.',
                ],
            ]);
        $this->app->instance(AIClientInterface::class, $ai);

        $response = $this->postJson('/api/student/chatbot/conversations', [
            'mode' => ChatbotConversation::MODE_SKILLS_MARKET_ANALYSIS,
            'message' => 'أنا هدفي ألاقي شغل Backend أسرع، وين أكبر نقطة ضعف عندي؟',
            'client_message_id' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.processing_status', 'completed')
            ->assertJsonPath('data.user_message.status', ChatbotMessage::STATUS_COMPLETED)
            ->assertJsonPath('data.assistant_message.status', ChatbotMessage::STATUS_COMPLETED)
            ->assertJsonPath(
                'data.assistant_message.content',
                'أكبر فجوة لديك هي Laravel: مستواك 0 بينما المستوى المطلوب 2.5.',
            );

        $conversationId = (int) $response->json('data.conversation.id');

        $this->assertDatabaseHas('chatbot_conversations', [
            'id' => $conversationId,
            'student_id' => $student->id,
            'mode' => ChatbotConversation::MODE_SKILLS_MARKET_ANALYSIS,
        ]);
        $this->assertDatabaseCount('chatbot_messages', 2);
    }

    public function test_opportunity_matching_full_http_flow_returns_flutter_action(): void
    {
        $student = $this->authenticateStudent();
        $this->addSkill($student);

        config([
            'chatbot.intent_classification.ai_fallback_enabled' => true,
            'chatbot.response_formatter.enabled' => true,
        ]);

        $opportunity = new Opportunity([
            'title' => 'Backend Internship',
            'type' => 'internship',
            'location' => 'Remote',
        ]);
        $opportunity->id = 901;
        $opportunity->setAttribute('match_data', [
            'score' => 90,
            'matched_skills' => [
                ['name' => 'Python', 'match_type' => 'full'],
                ['name' => 'REST API', 'match_type' => 'full'],
            ],
            'missing_skills' => [
                ['name' => 'Git'],
            ],
        ]);

        $recommendation = Mockery::mock(OpportunityRecommendationService::class);
        $recommendation->shouldReceive('getRecommendedForStudent')
            ->once()
            ->with($student->id)
            ->andReturn(collect([$opportunity]));
        $this->app->instance(OpportunityRecommendationService::class, $recommendation);

        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->twice()
            ->andReturnValues([
                ['intent' => 'find_and_explain_opportunities'],
                [
                    'content' => 'فرصة Backend Internship مناسبة لك بنسبة 90%؛ تمتلك Python وREST API وتحتاج إلى تطوير Git.',
                ],
            ]);
        $this->app->instance(AIClientInterface::class, $ai);

        $response = $this->postJson('/api/student/chatbot/conversations', [
            'mode' => ChatbotConversation::MODE_OPPORTUNITY_MATCHING,
            'message' => 'شو في شي بيناسب خبرتي الحالية؟',
            'client_message_id' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.processing_status', 'completed')
            ->assertJsonPath('data.assistant_message.status', ChatbotMessage::STATUS_COMPLETED)
            ->assertJsonPath(
                'data.assistant_message.content',
                'فرصة Backend Internship مناسبة لك بنسبة 90%؛ تمتلك Python وREST API وتحتاج إلى تطوير Git.',
            )
            ->assertJsonPath('data.assistant_message.actions.0.type', 'open_opportunity')
            ->assertJsonPath('data.assistant_message.actions.0.opportunity_id', 901);

        self::assertSame(
            'عرض فرصة Backend Internship',
            $response->json('data.assistant_message.actions.0.label'),
        );
    }

    public function test_wrong_mode_question_is_rejected_before_business_matching_runs(): void
    {
        $student = $this->authenticateStudent();
        $this->addSkill($student);

        config([
            'chatbot.intent_classification.ai_fallback_enabled' => true,
            'chatbot.response_formatter.enabled' => true,
        ]);

        $recommendation = Mockery::mock(OpportunityRecommendationService::class);
        $recommendation->shouldNotReceive('getRecommendedForStudent');
        $this->app->instance(OpportunityRecommendationService::class, $recommendation);

        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn(['intent' => 'out_of_scope']);
        $this->app->instance(AIClientInterface::class, $ai);

        $response = $this->postJson('/api/student/chatbot/conversations', [
            'mode' => ChatbotConversation::MODE_OPPORTUNITY_MATCHING,
            'message' => 'ما المهارات المسجلة عندي؟',
            'client_message_id' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.processing_status', 'completed')
            ->assertJsonPath('data.assistant_message.actions', []);

        self::assertStringContainsString(
            'هذا القسم مخصص للبحث عن فرص',
            (string) $response->json('data.assistant_message.content'),
        );
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

    private function addSkill(User $student): void
    {
        $token = Str::lower(Str::random(10));
        $skill = Skill::query()->create([
            'name' => 'E2E Skill '.$token,
            'category' => 'testing',
            'normalized_name' => 'e2e_skill_'.$token,
        ]);

        UserSkill::query()->create([
            'UserId' => $student->id,
            'SkillId' => $skill->id,
            'ProficiencyLevel' => 2,
            'ConfidenceScore' => 0.90,
            'Source' => 'e2e_test',
            'Verified' => false,
        ]);
    }

    private function seedPlatformKnowledge(): void
    {
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

        ChatbotKnowledgeEntry::query()->updateOrCreate(
            ['key' => 'find_assessment_or_evaluation_result'],
            [
                'category' => 'platform_help',
                'question_ar' => 'أين أجد نتيجة الاختبار أو التقييم؟',
                'question_en' => 'Where can I see my assessment result?',
                'answer_ar' => 'يمكنك العثور على نتيجة الاختبار من قسم النتائج.',
                'answer_en' => 'You can find your assessment result in the assessments or results section.',
                'keywords' => [
                    'ar' => ['نتيجة الاختبار', 'نتيجة التقييم'],
                    'en' => ['assessment result', 'evaluation result'],
                ],
                'action' => null,
                'is_active' => true,
            ],
        );
    }

    private function skillsMarketFacts(): array
    {
        return [
            'registered_skills' => [
                ['skill_name' => 'Python', 'proficiency_level' => 3],
                ['skill_name' => 'REST API', 'proficiency_level' => 2],
            ],
            'assessment' => [
                'available' => true,
                'career_path_name' => 'Backend Developer',
            ],
            'skill_gaps' => [
                [
                    'skill_name' => 'Laravel',
                    'actual_level' => 0,
                    'required_level' => 2.5,
                ],
            ],
            'learning_priorities' => [
                [
                    'skill_name' => 'Laravel',
                    'current_level' => 0,
                    'target_level' => 2.5,
                    'market' => ['demand_score' => 61.25],
                ],
            ],
            'market' => [
                'available' => true,
                'total_job_postings' => 37,
                'top_skills' => [
                    ['skill_name' => 'Python', 'demand_percentage' => 72.97],
                    ['skill_name' => 'SQL', 'demand_percentage' => 54.05],
                ],
            ],
        ];
    }
}
