<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Services\Chatbot\ChatbotOpportunityMatchPresenter;
use App\Services\Chatbot\ChatbotOpportunityQuestionResolver;
use App\Services\Chatbot\ChatbotOpportunityMatchingService;
use App\Services\Chatbot\ChatbotResponseFormatter;
use App\Services\Opportunities\OpportunityRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

class ChatbotOpportunityMatchingServiceTest extends TestCase
{
    use RefreshDatabase;
    use MockeryPHPUnitIntegration;

    public function test_it_returns_a_safe_message_without_calling_matching_when_student_has_no_skills(): void
    {
        [$student, $conversation, $userMessage] = $this->conversationWithMessage('ابحث لي عن فرصة', 'ar');
        $recommendation = Mockery::mock(OpportunityRecommendationService::class);
        $presenter = Mockery::mock(ChatbotOpportunityMatchPresenter::class);
        $formatter = Mockery::mock(ChatbotResponseFormatter::class);
        $resolver = $this->inScopeResolver($userMessage);
        $recommendation->shouldNotReceive('getRecommendedForStudent');
        $presenter->shouldNotReceive('present');
        $formatter->shouldNotReceive('format');

        $assistant = (new ChatbotOpportunityMatchingService(
            $recommendation,
            $presenter,
            $formatter,
            $resolver,
        ))->answer($conversation, $userMessage);

        self::assertFalse(UserSkill::query()->where('UserId', $student->id)->exists());
        self::assertSame(ChatbotMessage::STATUS_COMPLETED, $assistant->status);
        self::assertStringContainsString('قبل تسجيل مهارات', $assistant->content);
        self::assertNull($assistant->actions);
    }

    public function test_it_stores_presented_opportunities_and_flutter_actions(): void
    {
        [$student, $conversation, $userMessage] = $this->conversationWithMessage(
            'ابحث لي عن فرصة مناسبة واشرح السبب',
            'ar',
        );
        $this->addSkill($student);

        $opportunity = new Opportunity(['title' => 'Backend Internship']);
        $opportunity->id = 17;
        $recommendation = Mockery::mock(OpportunityRecommendationService::class);
        $presenter = Mockery::mock(ChatbotOpportunityMatchPresenter::class);
        $resolver = $this->inScopeResolver($userMessage);

        $recommendation->shouldReceive('getRecommendedForStudent')
            ->once()
            ->with($student->id)
            ->andReturn(collect([$opportunity]));
        $presenter->shouldReceive('present')
            ->once()
            ->with(Mockery::on(fn ($items): bool => $items->count() === 1), 'ar')
            ->andReturn([
                'content' => 'فرصة Backend Internship مناسبة لك بنسبة 90%.',
                'actions' => [[
                    'type' => 'open_opportunity',
                    'label' => 'عرض فرصة Backend Internship',
                    'opportunity_id' => 17,
                ]],
            ]);

        $formatter = $this->passthroughFormatter();

        $assistant = (new ChatbotOpportunityMatchingService(
            $recommendation,
            $presenter,
            $formatter,
            $resolver,
        ))->answer($conversation, $userMessage);

        self::assertSame(ChatbotMessage::STATUS_COMPLETED, $assistant->status);
        self::assertStringContainsString('90%', $assistant->content);
        self::assertSame('open_opportunity', $assistant->actions[0]['type']);
        self::assertSame(17, $assistant->actions[0]['opportunity_id']);
        self::assertSame(ChatbotMessage::STATUS_COMPLETED, $userMessage->fresh()->status);
    }

    public function test_it_marks_the_exchange_failed_when_recommendation_service_throws(): void
    {
        [$student, $conversation, $userMessage] = $this->conversationWithMessage(
            'Find a suitable opportunity',
            'en',
        );
        $this->addSkill($student);

        $recommendation = Mockery::mock(OpportunityRecommendationService::class);
        $presenter = Mockery::mock(ChatbotOpportunityMatchPresenter::class);
        $resolver = $this->inScopeResolver($userMessage);
        $recommendation->shouldReceive('getRecommendedForStudent')
            ->once()
            ->andThrow(new RuntimeException('Matching unavailable'));
        $presenter->shouldNotReceive('present');
        $formatter = Mockery::mock(ChatbotResponseFormatter::class);
        $formatter->shouldNotReceive('format');

        $assistant = (new ChatbotOpportunityMatchingService(
            $recommendation,
            $presenter,
            $formatter,
            $resolver,
        ))->answer($conversation, $userMessage);

        self::assertSame(ChatbotMessage::STATUS_FAILED, $assistant->status);
        self::assertSame('OPPORTUNITY_MATCHING_FAILED', $assistant->error_code);
        self::assertSame(ChatbotMessage::STATUS_FAILED, $userMessage->fresh()->status);
        self::assertSame('OPPORTUNITY_MATCHING_FAILED', $userMessage->fresh()->error_code);
    }



    public function test_out_of_scope_question_does_not_run_matching(): void
    {
        [$student, $conversation, $userMessage] = $this->conversationWithMessage(
            'ما المهارات المسجلة عندي؟',
            'ar',
        );
        $this->addSkill($student);

        $recommendation = Mockery::mock(OpportunityRecommendationService::class);
        $presenter = Mockery::mock(ChatbotOpportunityMatchPresenter::class);
        $formatter = Mockery::mock(ChatbotResponseFormatter::class);
        $resolver = Mockery::mock(ChatbotOpportunityQuestionResolver::class);

        $resolver->shouldReceive('resolve')
            ->once()
            ->with($userMessage->content, 'ar')
            ->andReturn(ChatbotOpportunityQuestionResolver::INTENT_OUT_OF_SCOPE);
        $recommendation->shouldNotReceive('getRecommendedForStudent');
        $presenter->shouldNotReceive('present');
        $formatter->shouldNotReceive('format');

        $assistant = (new ChatbotOpportunityMatchingService(
            $recommendation,
            $presenter,
            $formatter,
            $resolver,
        ))->answer($conversation, $userMessage);

        self::assertSame(ChatbotMessage::STATUS_COMPLETED, $assistant->status);
        self::assertStringContainsString('هذا القسم مخصص للبحث عن فرص', $assistant->content);
        self::assertNull($assistant->actions);
    }

    private function inScopeResolver(
        ChatbotMessage $userMessage,
    ): ChatbotOpportunityQuestionResolver {
        $resolver = Mockery::mock(ChatbotOpportunityQuestionResolver::class);
        $resolver->shouldReceive('resolve')
            ->once()
            ->with($userMessage->content, $userMessage->language)
            ->andReturn(ChatbotOpportunityQuestionResolver::INTENT_FIND_AND_EXPLAIN);

        return $resolver;
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
            'mode' => ChatbotConversation::MODE_OPPORTUNITY_MATCHING,
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

        return [$student, $conversation, $message];
    }

    private function addSkill(User $student): void
    {
        $token = Str::lower(Str::random(10));
        $skill = Skill::query()->create([
            'name' => 'Test Skill '.$token,
            'category' => 'testing',
            'normalized_name' => 'test_skill_'.$token,
        ]);

        UserSkill::query()->create([
            'UserId' => $student->id,
            'SkillId' => $skill->id,
            'ProficiencyLevel' => 2,
            'ConfidenceScore' => 0.90,
            'Source' => 'test',
            'Verified' => false,
        ]);
    }
}
