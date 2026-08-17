<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\User;
use App\Services\Chatbot\ChatbotResponseFormatter;
use App\Services\Chatbot\ChatbotSkillsMarketAnalysisService;
use App\Services\Chatbot\ChatbotSkillsMarketDataService;
use App\Services\Chatbot\ChatbotSkillsMarketQuestionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

class ChatbotSkillsMarketAnalysisServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_it_formats_registered_skills_from_backend_facts_and_completes_both_messages(): void
    {
        [$conversation, $userMessage] = $this->conversationWithMessage('ما المهارات المسجلة لدي؟', 'ar');
        $resolver = Mockery::mock(ChatbotSkillsMarketQuestionResolver::class);
        $dataService = Mockery::mock(ChatbotSkillsMarketDataService::class);

        $resolver->shouldReceive('resolve')
            ->once()
            ->with($userMessage->content, 'ar')
            ->andReturn(ChatbotSkillsMarketQuestionResolver::INTENT_REGISTERED_SKILLS);
        $dataService->shouldReceive('build')
            ->once()
            ->with($conversation->student_id)
            ->andReturn($this->facts());

        $formatter = $this->passthroughFormatter();

        $assistant = (new ChatbotSkillsMarketAnalysisService(
            $resolver,
            $dataService,
            $formatter,
        ))->answer($conversation, $userMessage);

        self::assertSame(ChatbotMessage::STATUS_COMPLETED, $assistant->status);
        self::assertStringContainsString('Python (المستوى 3)', $assistant->content);
        self::assertStringContainsString('REST API (المستوى 2)', $assistant->content);
        self::assertSame(ChatbotMessage::STATUS_COMPLETED, $userMessage->fresh()->status);
        self::assertNotNull($conversation->fresh()->last_message_at);
    }

    public function test_it_formats_market_demand_in_english_without_changing_percentages(): void
    {
        [$conversation, $userMessage] = $this->conversationWithMessage(
            'What are the most demanded skills?',
            'en',
        );
        $resolver = Mockery::mock(ChatbotSkillsMarketQuestionResolver::class);
        $dataService = Mockery::mock(ChatbotSkillsMarketDataService::class);

        $resolver->shouldReceive('resolve')
            ->once()
            ->andReturn(ChatbotSkillsMarketQuestionResolver::INTENT_MARKET_DEMAND);
        $dataService->shouldReceive('build')
            ->once()
            ->andReturn($this->facts());

        $formatter = $this->passthroughFormatter();

        $assistant = (new ChatbotSkillsMarketAnalysisService(
            $resolver,
            $dataService,
            $formatter,
        ))->answer($conversation, $userMessage);

        self::assertStringContainsString('Backend Developer', $assistant->content);
        self::assertStringContainsString('Python (72.97%)', $assistant->content);
        self::assertStringContainsString('SQL (54.05%)', $assistant->content);
    }

    public function test_it_formats_learning_priority_and_preserves_the_reason(): void
    {
        [$conversation, $userMessage] = $this->conversationWithMessage('بأي مهارة أبدأ ولماذا؟', 'ar');
        $resolver = Mockery::mock(ChatbotSkillsMarketQuestionResolver::class);
        $dataService = Mockery::mock(ChatbotSkillsMarketDataService::class);

        $resolver->shouldReceive('resolve')
            ->once()
            ->andReturn(ChatbotSkillsMarketQuestionResolver::INTENT_NEXT_STEP);
        $dataService->shouldReceive('build')
            ->once()
            ->andReturn($this->facts());

        $formatter = $this->passthroughFormatter();

        $assistant = (new ChatbotSkillsMarketAnalysisService(
            $resolver,
            $dataService,
            $formatter,
        ))->answer($conversation, $userMessage);

        self::assertStringContainsString('Laravel', $assistant->content);
        self::assertStringContainsString('من المستوى 0 إلى 2.5', $assistant->content);
        self::assertStringContainsString('طلب السوق 61.25%', $assistant->content);
    }

    public function test_it_marks_the_exchange_failed_when_data_collection_throws(): void
    {
        [$conversation, $userMessage] = $this->conversationWithMessage('ما مهاراتي؟', 'ar');
        $resolver = Mockery::mock(ChatbotSkillsMarketQuestionResolver::class);
        $dataService = Mockery::mock(ChatbotSkillsMarketDataService::class);

        $resolver->shouldReceive('resolve')
            ->once()
            ->andReturn(ChatbotSkillsMarketQuestionResolver::INTENT_REGISTERED_SKILLS);
        $dataService->shouldReceive('build')
            ->once()
            ->andThrow(new RuntimeException('Data unavailable'));

        $formatter = Mockery::mock(ChatbotResponseFormatter::class);
        $formatter->shouldNotReceive('format');

        $assistant = (new ChatbotSkillsMarketAnalysisService(
            $resolver,
            $dataService,
            $formatter,
        ))->answer($conversation, $userMessage);

        self::assertSame(ChatbotMessage::STATUS_FAILED, $assistant->status);
        self::assertSame('SKILLS_MARKET_ANALYSIS_FAILED', $assistant->error_code);
        self::assertSame(ChatbotMessage::STATUS_FAILED, $userMessage->fresh()->status);
        self::assertSame(
            'SKILLS_MARKET_ANALYSIS_FAILED',
            $userMessage->fresh()->error_code,
        );
    }

    public function test_out_of_scope_question_does_not_read_student_data_or_call_formatter(): void
    {
        [$conversation, $userMessage] = $this->conversationWithMessage(
            'اكتب لي متجر Laravel كامل',
            'ar',
        );
        $resolver = Mockery::mock(ChatbotSkillsMarketQuestionResolver::class);
        $dataService = Mockery::mock(ChatbotSkillsMarketDataService::class);
        $formatter = Mockery::mock(ChatbotResponseFormatter::class);

        $resolver->shouldReceive('resolve')
            ->once()
            ->with($userMessage->content, 'ar')
            ->andReturn(ChatbotSkillsMarketQuestionResolver::INTENT_OUT_OF_SCOPE);
        $dataService->shouldNotReceive('build');
        $formatter->shouldNotReceive('format');

        $assistant = (new ChatbotSkillsMarketAnalysisService(
            $resolver,
            $dataService,
            $formatter,
        ))->answer($conversation, $userMessage);

        self::assertSame(ChatbotMessage::STATUS_COMPLETED, $assistant->status);
        self::assertStringContainsString('هذا القسم مخصص لمهاراتك', $assistant->content);
        self::assertSame(ChatbotMessage::STATUS_COMPLETED, $userMessage->fresh()->status);
    }

    private function passthroughFormatter(): ChatbotResponseFormatter
    {
        $formatter = Mockery::mock(ChatbotResponseFormatter::class);
        $formatter->shouldReceive('format')
            ->once()
            ->andReturnUsing(
                fn (
                    string $mode,
                    string $language,
                    string $templateContent,
                    array $facts,
                    array $guard,
                ): string => $templateContent,
            );

        return $formatter;
    }

    private function conversationWithMessage(string $content, string $language): array
    {
        $student = User::factory()->create();
        $conversation = ChatbotConversation::query()->create([
            'student_id' => $student->id,
            'mode' => ChatbotConversation::MODE_SKILLS_MARKET_ANALYSIS,
            'title' => 'Test',
            'last_message_at' => now(),
        ]);
        $message = $conversation->messages()->create([
            'client_message_id' => null,
            'role' => ChatbotMessage::ROLE_USER,
            'content' => $content,
            'language' => $language,
            'status' => ChatbotMessage::STATUS_PENDING,
            'actions' => null,
            'error_code' => null,
        ]);

        return [$conversation, $message];
    }

    private function facts(): array
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
