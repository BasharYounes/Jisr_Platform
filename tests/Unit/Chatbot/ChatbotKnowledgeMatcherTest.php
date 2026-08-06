<?php

namespace Tests\Unit\Chatbot;

use App\Models\ChatbotKnowledgeEntry;
use App\Services\Chatbot\ChatbotKnowledgeMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatbotKnowledgeMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'chatbot.knowledge_matching.minimum_score' => 0.62,
            'chatbot.knowledge_matching.ambiguity_margin' => 0.08,
        ]);
    }

    public function test_it_matches_an_arabic_question_after_normalization(): void
    {
        $entry = $this->entry([
            'question_ar' => 'أين أرفع وثيقة السيرة التجريبية',
            'keywords' => ['ar' => ['رفع وثيقة السيرة التجريبية'], 'en' => []],
        ]);

        $matched = (new ChatbotKnowledgeMatcher())->match(
            'اين ارفع وثيقة السيرة التجريبية؟',
            'ar',
        );

        self::assertNotNull($matched);
        self::assertSame($entry->id, $matched->id);
    }

    public function test_it_matches_an_exact_english_question(): void
    {
        $entry = $this->entry([
            'question_en' => 'Where is the unique experimental dashboard?',
        ]);

        $matched = (new ChatbotKnowledgeMatcher())->match(
            'Where is the unique experimental dashboard?',
            'en',
        );

        self::assertNotNull($matched);
        self::assertSame($entry->id, $matched->id);
    }

    public function test_it_ignores_inactive_entries(): void
    {
        $this->entry([
            'question_en' => 'zxqv inactive intent 48291',
            'is_active' => false,
        ]);

        self::assertNull((new ChatbotKnowledgeMatcher())->match(
            'zxqv inactive intent 48291',
            'en',
        ));
    }

    public function test_a_single_generic_word_does_not_select_an_answer_from_a_long_question(): void
    {
        $this->entry([
            'question_en' => 'quasar platform help topic',
            'keywords' => ['ar' => [], 'en' => ['quasar']],
        ]);

        self::assertNull((new ChatbotKnowledgeMatcher())->match(
            'quasar unrelated external request today',
            'en',
        ));
    }

    public function test_it_returns_null_when_two_entries_are_ambiguous(): void
    {
        $phrase = 'unique ambiguous chatbot phrase 93628';

        $this->entry(['question_en' => $phrase]);
        $this->entry(['question_en' => $phrase]);

        self::assertNull((new ChatbotKnowledgeMatcher())->match($phrase, 'en'));
    }

    private function entry(array $overrides = []): ChatbotKnowledgeEntry
    {
        $token = Str::lower(Str::random(10));

        return ChatbotKnowledgeEntry::query()->create([
            'key' => 'test_'.$token,
            'category' => 'platform_help',
            'question_ar' => 'سؤال تجريبي '.$token,
            'question_en' => 'Experimental question '.$token,
            'answer_ar' => 'إجابة تجريبية.',
            'answer_en' => 'Experimental answer.',
            'keywords' => ['ar' => [], 'en' => []],
            'action' => null,
            'is_active' => true,
            ...$overrides,
        ]);
    }
}
