<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\UserSkill;
use App\Services\Opportunities\OpportunityRecommendationService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChatbotOpportunityMatchingService
{
    public function __construct(
        private readonly OpportunityRecommendationService $recommendationService,
        private readonly ChatbotOpportunityMatchPresenter $presenter,
        private readonly ChatbotResponseFormatter $responseFormatter,
        private readonly ChatbotOpportunityQuestionResolver $questionResolver,
    ) {}

    public function answer(
        ChatbotConversation $conversation,
        ChatbotMessage $userMessage,
    ): ChatbotMessage {
        $language = $userMessage->language === 'en' ? 'en' : 'ar';

        try {
            $intent = $this->questionResolver->resolve(
                $userMessage->content,
                $language,
            );

            if ($intent === ChatbotOpportunityQuestionResolver::INTENT_OUT_OF_SCOPE) {
                return $this->storeSuccessfulAnswer(
                    conversation: $conversation,
                    userMessage: $userMessage,
                    language: $language,
                    content: $this->outOfScopeAnswer($language),
                    actions: [],
                );
            }

            if (! $this->studentHasSkills((int) $conversation->student_id)) {
                return $this->storeSuccessfulAnswer(
                    conversation: $conversation,
                    userMessage: $userMessage,
                    language: $language,
                    content: $language === 'en'
                        ? 'I cannot search for a suitable opportunity before skills are registered in your Jisr profile.'
                        : 'لا يمكنني البحث عن فرصة مناسبة قبل تسجيل مهارات في ملفك على منصة جسر.',
                    actions: [],
                );
            }

            $limit = max(1, (int) config('chatbot.opportunity_matching.result_limit', 3));
            $opportunities = $this->recommendationService
                ->getRecommendedForStudent((int) $conversation->student_id)
                ->take($limit)
                ->values();

            $presented = $this->presenter->present($opportunities, $language);
            $formatting = $this->formattingContext($opportunities);

            $content = $this->responseFormatter->format(
                mode: ChatbotConversation::MODE_OPPORTUNITY_MATCHING,
                language: $language,
                templateContent: $presented['content'],
                facts: $formatting['facts'],
                guard: $formatting['guard'],
            );

            return $this->storeSuccessfulAnswer(
                conversation: $conversation,
                userMessage: $userMessage,
                language: $language,
                content: $content,
                actions: $presented['actions'],
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->storeFailedAnswer(
                conversation: $conversation,
                userMessage: $userMessage,
                language: $language,
            );
        }
    }


    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Opportunity>  $opportunities
     * @return array{
     *     facts: array<string, mixed>,
     *     guard: array{
     *         required_tokens: array<int, string>,
     *         ordered_tokens: array<int, string>,
     *         allowed_percentages: array<int, string|int|float>
     *     }
     * }
     */
    private function formattingContext(
        \Illuminate\Support\Collection $opportunities,
    ): array {
        if ($opportunities->isEmpty()) {
            return [
                'facts' => [],
                'guard' => [
                    'required_tokens' => [],
                    'ordered_tokens' => [],
                    'allowed_percentages' => [],
                ],
            ];
        }

        $facts = $opportunities
            ->values()
            ->map(function ($opportunity, int $index): array {
                $match = is_array($opportunity->match_data ?? null)
                    ? $opportunity->match_data
                    : [];

                return [
                    'rank' => $index + 1,
                    'title' => (string) ($opportunity->title ?? ''),
                    'match_percentage' => $this->number($match['score'] ?? 0),
                    'type' => $opportunity->type,
                    'location' => $opportunity->location,
                    'fully_matched_skills' => collect($match['matched_skills'] ?? [])
                        ->where('match_type', 'full')
                        ->pluck('name')
                        ->filter()
                        ->values()
                        ->all(),
                    'partially_matched_skills' => collect($match['matched_skills'] ?? [])
                        ->where('match_type', 'partial')
                        ->pluck('name')
                        ->filter()
                        ->values()
                        ->all(),
                    'missing_skills' => collect($match['missing_skills'] ?? [])
                        ->pluck('name')
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $item): bool => $item['title'] !== '')
            ->values();

        if ($facts->isEmpty()) {
            return [
                'facts' => [],
                'guard' => [
                    'required_tokens' => [],
                    'ordered_tokens' => [],
                    'allowed_percentages' => [],
                ],
            ];
        }

        $percentages = $facts->pluck('match_percentage')->all();

        $requiredTokens = $facts
            ->flatMap(function (array $item): array {
                return collect([
                    $item['title'],
                    "{$item['match_percentage']}%",
                ])
                    ->merge($item['fully_matched_skills'])
                    ->merge($item['partially_matched_skills'])
                    ->merge($item['missing_skills'])
                    ->values()
                    ->all();
            })
            ->values()
            ->all();

        return [
            'facts' => [
                'ranking_basis' => [
                    'registered_skill_levels',
                    'required_skill_levels',
                    'skill_weights',
                    'mandatory_skill_eligibility',
                ],
                'opportunities' => $facts->all(),
            ],
            'guard' => [
                'required_tokens' => $requiredTokens,
                'ordered_tokens' => $facts->pluck('title')->all(),
                'allowed_percentages' => $percentages,
            ],
        ];
    }

    private function number(mixed $value): string
    {
        $number = (float) $value;

        return fmod($number, 1.0) === 0.0
            ? (string) (int) $number
            : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function outOfScopeAnswer(string $language): string
    {
        return $language === 'en'
            ? 'This section is for finding suitable opportunities and explaining why they match your skills. For questions about your skills or labor-market analysis, use the skills-and-market section.'
            : 'هذا القسم مخصص للبحث عن فرص مناسبة وشرح سبب ملاءمتها لمهاراتك. وللأسئلة المتعلقة بمهاراتك أو تحليل سوق العمل استخدم قسم مهاراتي وسوق العمل.';
    }

    private function studentHasSkills(int $studentId): bool
    {
        return UserSkill::query()
            ->where('UserId', $studentId)
            ->exists();
    }

    private function storeSuccessfulAnswer(
        ChatbotConversation $conversation,
        ChatbotMessage $userMessage,
        string $language,
        string $content,
        array $actions,
    ): ChatbotMessage {
        return DB::transaction(function () use (
            $conversation,
            $userMessage,
            $language,
            $content,
            $actions,
        ): ChatbotMessage {
            $userMessage->update([
                'status' => ChatbotMessage::STATUS_COMPLETED,
                'error_code' => null,
            ]);

            $assistantMessage = $conversation->messages()->create([
                'client_message_id' => null,
                'role' => ChatbotMessage::ROLE_ASSISTANT,
                'content' => $content,
                'language' => $language,
                'status' => ChatbotMessage::STATUS_COMPLETED,
                'actions' => $actions !== [] ? $actions : null,
                'error_code' => null,
            ]);

            $conversation->update([
                'last_message_at' => $assistantMessage->created_at,
            ]);

            return $assistantMessage;
        });
    }

    private function storeFailedAnswer(
        ChatbotConversation $conversation,
        ChatbotMessage $userMessage,
        string $language,
    ): ChatbotMessage {
        $content = $language === 'en'
            ? 'Opportunity matching is temporarily unavailable. Please try again.'
            : 'تعذر البحث عن الفرص المناسبة مؤقتًا. حاول مرة أخرى.';

        return DB::transaction(function () use (
            $conversation,
            $userMessage,
            $language,
            $content,
        ): ChatbotMessage {
            $userMessage->update([
                'status' => ChatbotMessage::STATUS_FAILED,
                'error_code' => 'OPPORTUNITY_MATCHING_FAILED',
            ]);

            $assistantMessage = $conversation->messages()->create([
                'client_message_id' => null,
                'role' => ChatbotMessage::ROLE_ASSISTANT,
                'content' => $content,
                'language' => $language,
                'status' => ChatbotMessage::STATUS_FAILED,
                'actions' => null,
                'error_code' => 'OPPORTUNITY_MATCHING_FAILED',
            ]);

            $conversation->update([
                'last_message_at' => $assistantMessage->created_at,
            ]);

            return $assistantMessage;
        });
    }
}
