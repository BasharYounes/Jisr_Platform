<?php

namespace Tests\Unit\Chatbot;

use App\Services\AI\AIClientInterface;
use App\Services\Chatbot\ChatbotResponseFactGuard;
use App\Services\Chatbot\ChatbotResponseFormatter;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

class ChatbotResponseFormatterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('chatbot.response_formatter.enabled', true);
        config()->set('chatbot.response_formatter.modes', [
            'skills_market_analysis',
            'opportunity_matching',
        ]);
        config()->set('chatbot.response_formatter.max_output_length', 2500);
        config()->set('chatbot.response_formatter.task_type', 'default');
    }

    public function test_it_accepts_a_natural_response_that_preserves_the_backend_facts(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn([
                'content' => 'ضمن مسار Backend Developer، تتصدر Python الطلب بنسبة 72.97%.',
            ]);

        $result = $this->formatter($ai)->format(
            mode: 'skills_market_analysis',
            language: 'ar',
            templateContent: 'أكثر المهارات طلبًا لمسار Backend Developer هي Python (72.97%).',
            facts: [
                'career_path' => 'Backend Developer',
                'skill' => 'Python',
                'demand_percentage' => 72.97,
            ],
            guard: [
                'required_tokens' => ['Backend Developer', 'Python', '72.97%'],
                'ordered_tokens' => ['Backend Developer', 'Python'],
                'allowed_percentages' => [72.97],
            ],
        );

        self::assertSame(
            'ضمن مسار Backend Developer، تتصدر Python الطلب بنسبة 72.97%.',
            $result,
        );
    }

    public function test_it_accepts_a_decimal_fact_followed_by_sentence_punctuation(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn([
                'content' => 'أكبر فجوة لديك هي Laravel: مستواك 0 بينما المستوى المطلوب 2.5.',
            ]);

        $result = $this->formatter($ai)->format(
            mode: 'skills_market_analysis',
            language: 'ar',
            templateContent: 'أعلى فجوات المهارات لديك حاليًا هي: Laravel: مستواك 0 والمطلوب 2.5.',
            facts: [
                'skill_name' => 'Laravel',
                'current_level' => 0,
                'required_level' => 2.5,
            ],
            guard: [
                'required_tokens' => ['Laravel', '0', '2.5'],
                'ordered_tokens' => ['Laravel'],
                'allowed_percentages' => [],
            ],
        );

        self::assertSame(
            'أكبر فجوة لديك هي Laravel: مستواك 0 بينما المستوى المطلوب 2.5.',
            $result,
        );
    }

    public function test_it_returns_the_template_when_formatting_is_disabled(): void
    {
        config()->set('chatbot.response_formatter.enabled', false);

        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldNotReceive('generateJson');

        $result = $this->formatter($ai)->format(
            mode: 'skills_market_analysis',
            language: 'ar',
            templateContent: 'النص الآمن.',
            facts: ['skill' => 'Python'],
            guard: ['required_tokens' => ['Python']],
        );

        self::assertSame('النص الآمن.', $result);
    }

    public function test_it_returns_the_template_for_a_mode_that_is_not_approved_for_ai_formatting(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldNotReceive('generateJson');

        $result = $this->formatter($ai)->format(
            mode: 'platform_help',
            language: 'ar',
            templateContent: 'إجابة قاعدة المعرفة.',
            facts: ['knowledge_key' => 'cv_upload'],
        );

        self::assertSame('إجابة قاعدة المعرفة.', $result);
    }

    public function test_it_rejects_a_response_that_drops_a_required_fact(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn([
                'content' => 'تعد Python من المهارات المطلوبة في السوق.',
            ]);

        $template = 'ضمن مسار Backend Developer، الطلب على Python هو 72.97%.';

        $result = $this->formatter($ai)->format(
            mode: 'skills_market_analysis',
            language: 'ar',
            templateContent: $template,
            facts: ['career_path' => 'Backend Developer'],
            guard: [
                'required_tokens' => ['Backend Developer', 'Python', '72.97%'],
                'allowed_percentages' => [72.97],
            ],
        );

        self::assertSame($template, $result);
    }

    public function test_it_rejects_a_response_that_introduces_a_different_percentage(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn([
                'content' => 'فرصة Backend Internship مناسبة بنسبة 90%، وثقتنا 95%.',
            ]);

        $template = 'فرصة Backend Internship مناسبة بنسبة 90%.';

        $result = $this->formatter($ai)->format(
            mode: 'opportunity_matching',
            language: 'ar',
            templateContent: $template,
            facts: [
                'title' => 'Backend Internship',
                'match_percentage' => 90,
            ],
            guard: [
                'required_tokens' => ['Backend Internship', '90%'],
                'allowed_percentages' => [90],
            ],
        );

        self::assertSame($template, $result);
    }

    public function test_it_rejects_a_response_that_changes_the_opportunity_order(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andReturn([
                'content' => '2) Second Opportunity بنسبة 80%. 1) First Opportunity بنسبة 90%.',
            ]);

        $template = '1) First Opportunity بنسبة 90%. 2) Second Opportunity بنسبة 80%.';

        $result = $this->formatter($ai)->format(
            mode: 'opportunity_matching',
            language: 'ar',
            templateContent: $template,
            facts: ['count' => 2],
            guard: [
                'required_tokens' => [
                    'First Opportunity',
                    '90%',
                    'Second Opportunity',
                    '80%',
                ],
                'ordered_tokens' => ['First Opportunity', 'Second Opportunity'],
                'allowed_percentages' => [90, 80],
            ],
        );

        self::assertSame($template, $result);
    }

    public function test_it_falls_back_safely_when_the_provider_throws(): void
    {
        $ai = Mockery::mock(AIClientInterface::class);
        $ai->shouldReceive('generateJson')
            ->once()
            ->andThrow(new RuntimeException('Gemini unavailable'));

        $result = $this->formatter($ai)->format(
            mode: 'skills_market_analysis',
            language: 'ar',
            templateContent: 'الإجابة الآمنة الأصلية.',
            facts: ['skill' => 'Laravel'],
            guard: ['required_tokens' => ['Laravel']],
        );

        self::assertSame('الإجابة الآمنة الأصلية.', $result);
    }

    private function formatter(AIClientInterface $ai): ChatbotResponseFormatter
    {
        return new ChatbotResponseFormatter(
            aiClient: $ai,
            factGuard: new ChatbotResponseFactGuard,
        );
    }
}
