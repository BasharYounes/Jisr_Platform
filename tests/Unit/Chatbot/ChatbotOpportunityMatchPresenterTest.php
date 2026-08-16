<?php

namespace Tests\Unit\Chatbot;

use App\Models\Opportunity;
use App\Services\Chatbot\ChatbotOpportunityMatchPresenter;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ChatbotOpportunityMatchPresenterTest extends TestCase
{
    public function test_it_explains_full_partial_and_missing_skills_in_arabic(): void
    {
        $opportunity = $this->opportunity(
            id: 15,
            title: 'Backend Internship',
            score: 87.5,
            full: ['Python'],
            partial: ['REST API'],
            missing: ['Git'],
        );

        $result = (new ChatbotOpportunityMatchPresenter())->present(
            new Collection([$opportunity]),
            'ar',
        );

        self::assertStringContainsString('Backend Internship', $result['content']);
        self::assertStringContainsString('87.5%', $result['content']);
        self::assertStringContainsString('تمتلك بالمستوى المطلوب: Python', $result['content']);
        self::assertStringContainsString('تمتلك بمستوى أقل من المطلوب: REST API', $result['content']);
        self::assertStringContainsString('مهارات تحتاج إلى تطويرها: Git', $result['content']);
        self::assertSame('open_opportunity', $result['actions'][0]['type']);
        self::assertSame(15, $result['actions'][0]['opportunity_id']);
    }

    public function test_it_formats_an_english_answer_and_action(): void
    {
        $opportunity = $this->opportunity(
            id: 21,
            title: 'API Trainee',
            score: 100,
            full: ['PHP', 'Laravel'],
        );

        $result = (new ChatbotOpportunityMatchPresenter())->present(
            collect([$opportunity]),
            'en',
        );

        self::assertStringContainsString('The best currently suitable opportunities are:', $result['content']);
        self::assertStringContainsString('match 100%', $result['content']);
        self::assertStringContainsString('You fully match: PHP, Laravel', $result['content']);
        self::assertSame('View API Trainee', $result['actions'][0]['label']);
    }

    public function test_it_preserves_the_recommendation_order(): void
    {
        $first = $this->opportunity(id: 1, title: 'First Match', score: 95);
        $second = $this->opportunity(id: 2, title: 'Second Match', score: 75);

        $result = (new ChatbotOpportunityMatchPresenter())->present(
            collect([$first, $second]),
            'en',
        );

        self::assertLessThan(
            strpos($result['content'], 'Second Match'),
            strpos($result['content'], 'First Match'),
        );
        self::assertSame([1, 2], array_column($result['actions'], 'opportunity_id'));
    }

    public function test_it_returns_a_clear_empty_state_without_actions(): void
    {
        $result = (new ChatbotOpportunityMatchPresenter())->present(
            collect(),
            'en',
        );

        self::assertStringContainsString('could not find', $result['content']);
        self::assertSame([], $result['actions']);
    }

    private function opportunity(
        int $id,
        string $title,
        float|int $score,
        array $full = [],
        array $partial = [],
        array $missing = [],
    ): Opportunity {
        $opportunity = new Opportunity([
            'title' => $title,
            'type' => 'internship',
            'location' => 'Remote',
        ]);
        $opportunity->id = $id;
        $opportunity->match_data = [
            'score' => $score,
            'matched_skills' => [
                ...array_map(
                    static fn (string $name): array => ['name' => $name, 'match_type' => 'full'],
                    $full,
                ),
                ...array_map(
                    static fn (string $name): array => ['name' => $name, 'match_type' => 'partial'],
                    $partial,
                ),
            ],
            'missing_skills' => array_map(
                static fn (string $name): array => ['name' => $name, 'mandatory' => false],
                $missing,
            ),
        ];

        return $opportunity;
    }
}
