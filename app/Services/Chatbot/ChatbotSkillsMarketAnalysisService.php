<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChatbotSkillsMarketAnalysisService
{
    public function __construct(
        private readonly ChatbotSkillsMarketQuestionResolver $questionResolver,
        private readonly ChatbotSkillsMarketDataService $dataService,
        private readonly ChatbotResponseFormatter $responseFormatter,
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

            if ($intent === ChatbotSkillsMarketQuestionResolver::INTENT_OUT_OF_SCOPE) {
                return $this->storeSuccessfulAnswer(
                    conversation: $conversation,
                    userMessage: $userMessage,
                    language: $language,
                    content: $this->outOfScopeAnswer($language),
                );
            }

            $data = $this->dataService->build((int) $conversation->student_id);
            $templateContent = $this->buildAnswer($intent, $data, $language);
            $formatting = $this->formattingContext($intent, $data);

            $content = $this->responseFormatter->format(
                mode: ChatbotConversation::MODE_SKILLS_MARKET_ANALYSIS,
                language: $language,
                templateContent: $templateContent,
                facts: $formatting['facts'],
                guard: $formatting['guard'],
            );

            return $this->storeSuccessfulAnswer(
                conversation: $conversation,
                userMessage: $userMessage,
                language: $language,
                content: $content,
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

    private function outOfScopeAnswer(string $language): string
    {
        return $language === 'en'
            ? 'This section is for your skills and labor-market analysis. You can ask about your registered skills, levels, career path, skill gaps, market demand, or learning priority. To search for a suitable opportunity, use the suitable-opportunities section.'
            : 'هذا القسم مخصص لمهاراتك وتحليل سوق العمل. يمكنك السؤال عن مهاراتك المسجلة، مستوياتك، مسارك المهني، فجوات المهارات، طلب السوق أو أولوية التعلم. وللبحث عن فرصة مناسبة استخدم قسم الفرص المناسبة.';
    }

    private function buildAnswer(string $intent, array $data, string $language): string
    {
        return match ($intent) {
            ChatbotSkillsMarketQuestionResolver::INTENT_REGISTERED_SKILLS => $this->registeredSkillsAnswer($data, $language),
            ChatbotSkillsMarketQuestionResolver::INTENT_CURRENT_LEVEL => $this->currentLevelAnswer($data, $language),
            ChatbotSkillsMarketQuestionResolver::INTENT_CAREER_PATH => $this->careerPathAnswer($data, $language),
            ChatbotSkillsMarketQuestionResolver::INTENT_MISSING_SKILLS => $this->missingSkillsAnswer($data, $language),
            ChatbotSkillsMarketQuestionResolver::INTENT_MARKET_DEMAND => $this->marketDemandAnswer($data, $language),
            ChatbotSkillsMarketQuestionResolver::INTENT_COMPARISON => $this->comparisonAnswer($data, $language),
            ChatbotSkillsMarketQuestionResolver::INTENT_NEXT_STEP => $this->nextStepAnswer($data, $language),
            default => $this->summaryAnswer($data, $language),
        };
    }

    private function registeredSkillsAnswer(array $data, string $language): string
    {
        $skills = collect($data['registered_skills'] ?? []);

        if ($skills->isEmpty()) {
            return $language === 'en'
                ? 'There are no skills registered in your Jisr profile yet.'
                : 'لا توجد مهارات مسجلة في ملفك على منصة جسر حتى الآن.';
        }

        $list = $skills
            ->map(fn (array $skill) => $language === 'en'
                ? "{$skill['skill_name']} (level {$skill['proficiency_level']})"
                : "{$skill['skill_name']} (المستوى {$skill['proficiency_level']})")
            ->implode('، ');

        return $language === 'en'
            ? "Your registered skills are: {$list}."
            : "المهارات المسجلة لديك هي: {$list}.";
    }

    private function currentLevelAnswer(array $data, string $language): string
    {
        $skills = collect($data['registered_skills'] ?? []);

        if ($skills->isEmpty()) {
            return $language === 'en'
                ? 'No current skill levels are registered in your Jisr profile.'
                : 'لا توجد مستويات مهارات مسجلة في ملفك على منصة جسر حاليًا.';
        }

        $list = $skills
            ->map(fn (array $skill) => $language === 'en'
                ? "{$skill['skill_name']}: level {$skill['proficiency_level']}"
                : "{$skill['skill_name']}: المستوى {$skill['proficiency_level']}")
            ->implode('، ');

        return $language === 'en'
            ? "Jisr does not store one overall level for all skills. Your current recorded levels are: {$list}."
            : "لا يحفظ النظام مستوى عامًا واحدًا لكل المهارات. مستوياتك الحالية المسجلة هي: {$list}.";
    }

    private function careerPathAnswer(array $data, string $language): string
    {
        $pathName = $data['assessment']['career_path_name'] ?? null;

        if (! $pathName) {
            return $language === 'en'
                ? 'No career path is currently linked to your latest assessment session.'
                : 'لا يوجد مسار مهني مرتبط بأحدث جلسة تقييم لديك حاليًا.';
        }

        return $language === 'en'
            ? "Your selected career path is {$pathName}."
            : "مسارك المهني المختار هو {$pathName}.";
    }

    private function missingSkillsAnswer(array $data, string $language): string
    {
        if (! ($data['assessment']['available'] ?? false)) {
            return $language === 'en'
                ? 'Your missing skills cannot be calculated until you have an assessment session linked to a career path.'
                : 'لا يمكن حساب المهارات الناقصة قبل وجود جلسة تقييم مرتبطة بمسار مهني.';
        }

        $gaps = collect($data['skill_gaps'] ?? [])->take(5);

        if ($gaps->isEmpty()) {
            return $language === 'en'
                ? 'No skill gaps are currently shown for your selected career path.'
                : 'لا تظهر حاليًا فجوات مهارية ضمن مسارك المهني المختار.';
        }

        $list = $gaps
            ->map(fn (array $gap) => $language === 'en'
                ? sprintf(
                    '%s: current level %s, required level %s',
                    $gap['skill_name'] ?? 'Skill',
                    $this->number($gap['actual_level'] ?? 0),
                    $this->number($gap['required_level'] ?? 0),
                )
                : sprintf(
                    '%s: مستواك %s والمطلوب %s',
                    $gap['skill_name'] ?? 'مهارة',
                    $this->number($gap['actual_level'] ?? 0),
                    $this->number($gap['required_level'] ?? 0),
                ))
            ->implode('؛ ');

        return $language === 'en'
            ? "Your highest current skill gaps are: {$list}."
            : "أعلى فجوات المهارات لديك حاليًا هي: {$list}.";
    }

    private function marketDemandAnswer(array $data, string $language): string
    {
        $pathName = $data['assessment']['career_path_name'] ?? null;

        if (! $pathName) {
            return $language === 'en'
                ? 'A career path is required before market-demand skills can be shown for you.'
                : 'يجب أن يكون لديك مسار مهني محدد حتى نعرض المهارات المطلوبة له في سوق العمل.';
        }

        $skills = collect($data['market']['top_skills'] ?? [])->take(5);

        if ($skills->isEmpty()) {
            return $language === 'en'
                ? "There is not enough current market data for the {$pathName} path."
                : "لا توجد حاليًا بيانات سوق عمل كافية لمسار {$pathName}.";
        }

        $list = $skills
            ->map(fn (array $skill) => sprintf(
                '%s (%s%%)',
                $skill['skill_name'] ?? 'Skill',
                $this->number($skill['demand_percentage'] ?? 0),
            ))
            ->implode('، ');

        return $language === 'en'
            ? "The most demanded skills for the {$pathName} path are: {$list}. The percentages are based on the active job postings analyzed by Jisr."
            : "أكثر المهارات طلبًا لمسار {$pathName} هي: {$list}. النسب مبنية على إعلانات الوظائف النشطة التي حللتها منصة جسر.";
    }

    private function comparisonAnswer(array $data, string $language): string
    {
        $pathName = $data['assessment']['career_path_name'] ?? null;
        $owned = collect($data['registered_skills'] ?? []);
        $gaps = collect($data['skill_gaps'] ?? [])->take(5);

        if (! $pathName) {
            return $language === 'en'
                ? 'Your skills cannot be compared with the market until a career path is linked to your assessment.'
                : 'لا يمكن مقارنة مهاراتك بسوق العمل قبل ربط تقييمك بمسار مهني.';
        }

        $ownedText = $owned->isEmpty()
            ? ($language === 'en' ? 'no registered skills' : 'لا توجد مهارات مسجلة')
            : $owned->pluck('skill_name')->implode('، ');

        $missingText = $gaps->isEmpty()
            ? ($language === 'en' ? 'no calculated gaps' : 'لا توجد فجوات محسوبة')
            : $gaps->pluck('skill_name')->implode('، ');

        return $language === 'en'
            ? "For the {$pathName} path, your registered skills are: {$ownedText}. The highest calculated gaps are: {$missingText}."
            : "بالنسبة لمسار {$pathName}، مهاراتك المسجلة هي: {$ownedText}. وأعلى الفجوات المحسوبة لديك هي: {$missingText}.";
    }

    private function nextStepAnswer(array $data, string $language): string
    {
        $priorities = collect($data['learning_priorities'] ?? [])->take(3);

        if ($priorities->isEmpty()) {
            return $language === 'en'
                ? 'There is no calculated learning priority yet. Complete or update your level assessment first.'
                : 'لا توجد أولوية تعلم محسوبة حاليًا. أكمل اختبار تحديد المستوى أو حدّثه أولًا.';
        }

        $items = $priorities
            ->map(function (array $item) use ($language) {
                $marketScore = $item['market']['demand_score'] ?? null;
                $reason = $marketScore !== null
                    ? ($language === 'en'
                        ? "market demand {$this->number($marketScore)}%"
                        : "طلب السوق {$this->number($marketScore)}%")
                    : ($language === 'en'
                        ? 'based on your assessment gap'
                        : 'بناءً على فجوة التقييم');

                return $language === 'en'
                    ? sprintf(
                        '%s (from level %s to %s; %s)',
                        $item['skill_name'] ?? 'Skill',
                        $this->number($item['current_level'] ?? 0),
                        $this->number($item['target_level'] ?? 0),
                        $reason,
                    )
                    : sprintf(
                        '%s (من المستوى %s إلى %s؛ %s)',
                        $item['skill_name'] ?? 'مهارة',
                        $this->number($item['current_level'] ?? 0),
                        $this->number($item['target_level'] ?? 0),
                        $reason,
                    );
            })
            ->implode('، ');

        return $language === 'en'
            ? "Your recommended learning order is: {$items}. This order comes from your assessment gaps and the available market-demand data."
            : "ترتيب التعلم المقترح لك هو: {$items}. هذا الترتيب ناتج عن فجوات تقييمك وبيانات الطلب المتاحة في سوق العمل.";
    }

    private function summaryAnswer(array $data, string $language): string
    {
        $pathName = $data['assessment']['career_path_name'] ?? null;
        $skillsCount = count($data['registered_skills'] ?? []);
        $topGaps = collect($data['skill_gaps'] ?? [])->take(3)->pluck('skill_name')->implode('، ');
        $topMarket = collect($data['market']['top_skills'] ?? [])->take(3)->pluck('skill_name')->implode('، ');

        if ($language === 'en') {
            $pathText = $pathName ?: 'not selected';
            $gapText = $topGaps ?: 'none currently calculated';
            $marketText = $topMarket ?: 'no sufficient market data';

            return "Skills and market summary: career path: {$pathText}; registered skills: {$skillsCount}; highest gaps: {$gapText}; most demanded market skills: {$marketText}.";
        }

        $pathText = $pathName ?: 'غير محدد';
        $gapText = $topGaps ?: 'لا توجد فجوات محسوبة حاليًا';
        $marketText = $topMarket ?: 'لا توجد بيانات سوق كافية';

        return "ملخص المهارات والسوق: المسار المهني: {$pathText}؛ عدد المهارات المسجلة: {$skillsCount}؛ أعلى الفجوات: {$gapText}؛ أكثر مهارات السوق طلبًا: {$marketText}.";
    }

    /**
     * @return array{
     *     facts: array<string, mixed>,
     *     guard: array{
     *         required_tokens: array<int, string>,
     *         ordered_tokens: array<int, string>,
     *         allowed_percentages: array<int, string|int|float>
     *     }
     * }
     */
    private function formattingContext(string $intent, array $data): array
    {
        return match ($intent) {
            ChatbotSkillsMarketQuestionResolver::INTENT_REGISTERED_SKILLS,
            ChatbotSkillsMarketQuestionResolver::INTENT_CURRENT_LEVEL => $this->skillsFormattingContext($intent, $data),
            ChatbotSkillsMarketQuestionResolver::INTENT_CAREER_PATH => $this->careerPathFormattingContext($intent, $data),
            ChatbotSkillsMarketQuestionResolver::INTENT_MISSING_SKILLS => $this->gapsFormattingContext($intent, $data),
            ChatbotSkillsMarketQuestionResolver::INTENT_MARKET_DEMAND => $this->marketFormattingContext($intent, $data),
            ChatbotSkillsMarketQuestionResolver::INTENT_COMPARISON => $this->comparisonFormattingContext($intent, $data),
            ChatbotSkillsMarketQuestionResolver::INTENT_NEXT_STEP => $this->learningFormattingContext($intent, $data),
            default => $this->summaryFormattingContext($data),
        };
    }

    private function skillsFormattingContext(string $intent, array $data): array
    {
        $skills = collect($data['registered_skills'] ?? [])
            ->map(fn (array $skill): array => [
                'name' => (string) ($skill['skill_name'] ?? ''),
                'level' => $this->number($skill['proficiency_level'] ?? 0),
            ])
            ->filter(fn (array $skill): bool => $skill['name'] !== '')
            ->values();

        if ($skills->isEmpty()) {
            return $this->emptyFormattingContext();
        }

        return [
            'facts' => [
                'intent' => $intent,
                'skills' => $skills->all(),
            ],
            'guard' => [
                'required_tokens' => $skills
                    ->flatMap(fn (array $skill): array => [
                        $skill['name'],
                        $skill['level'],
                    ])
                    ->values()
                    ->all(),
                'ordered_tokens' => $skills->pluck('name')->all(),
                'allowed_percentages' => [],
            ],
        ];
    }

    private function careerPathFormattingContext(string $intent, array $data): array
    {
        $pathName = trim((string) ($data['assessment']['career_path_name'] ?? ''));

        if ($pathName === '') {
            return $this->emptyFormattingContext();
        }

        return [
            'facts' => [
                'intent' => $intent,
                'career_path' => $pathName,
            ],
            'guard' => [
                'required_tokens' => [$pathName],
                'ordered_tokens' => [],
                'allowed_percentages' => [],
            ],
        ];
    }

    private function gapsFormattingContext(string $intent, array $data): array
    {
        $gaps = collect($data['skill_gaps'] ?? [])
            ->take(5)
            ->map(fn (array $gap): array => [
                'skill_name' => (string) ($gap['skill_name'] ?? ''),
                'current_level' => $this->number($gap['actual_level'] ?? 0),
                'required_level' => $this->number($gap['required_level'] ?? 0),
            ])
            ->filter(fn (array $gap): bool => $gap['skill_name'] !== '')
            ->values();

        if ($gaps->isEmpty()) {
            return $this->emptyFormattingContext();
        }

        return [
            'facts' => [
                'intent' => $intent,
                'career_path' => $data['assessment']['career_path_name'] ?? null,
                'skill_gaps' => $gaps->all(),
            ],
            'guard' => [
                'required_tokens' => $gaps
                    ->flatMap(fn (array $gap): array => [
                        $gap['skill_name'],
                        $gap['current_level'],
                        $gap['required_level'],
                    ])
                    ->values()
                    ->all(),
                'ordered_tokens' => $gaps->pluck('skill_name')->all(),
                'allowed_percentages' => [],
            ],
        ];
    }

    private function marketFormattingContext(string $intent, array $data): array
    {
        $pathName = trim((string) ($data['assessment']['career_path_name'] ?? ''));
        $skills = collect($data['market']['top_skills'] ?? [])
            ->take(5)
            ->map(fn (array $skill): array => [
                'skill_name' => (string) ($skill['skill_name'] ?? ''),
                'demand_percentage' => $this->number($skill['demand_percentage'] ?? 0),
            ])
            ->filter(fn (array $skill): bool => $skill['skill_name'] !== '')
            ->values();

        if ($pathName === '' || $skills->isEmpty()) {
            return $this->emptyFormattingContext();
        }

        $percentages = $skills->pluck('demand_percentage')->all();

        return [
            'facts' => [
                'intent' => $intent,
                'career_path' => $pathName,
                'top_market_skills' => $skills->all(),
                'source' => 'active job postings analyzed by Jisr',
            ],
            'guard' => [
                'required_tokens' => collect([$pathName])
                    ->merge($skills->pluck('skill_name'))
                    ->merge(
                        collect($percentages)
                            ->map(fn (string $value): string => "{$value}%"),
                    )
                    ->values()
                    ->all(),
                'ordered_tokens' => $skills->pluck('skill_name')->all(),
                'allowed_percentages' => $percentages,
            ],
        ];
    }

    private function comparisonFormattingContext(string $intent, array $data): array
    {
        $pathName = trim((string) ($data['assessment']['career_path_name'] ?? ''));
        $ownedSkills = collect($data['registered_skills'] ?? [])
            ->pluck('skill_name')
            ->filter()
            ->map(fn (mixed $name): string => (string) $name)
            ->values();
        $gapSkills = collect($data['skill_gaps'] ?? [])
            ->take(5)
            ->pluck('skill_name')
            ->filter()
            ->map(fn (mixed $name): string => (string) $name)
            ->values();

        if ($pathName === '') {
            return $this->emptyFormattingContext();
        }

        return [
            'facts' => [
                'intent' => $intent,
                'career_path' => $pathName,
                'registered_skills' => $ownedSkills->all(),
                'highest_skill_gaps' => $gapSkills->all(),
            ],
            'guard' => [
                'required_tokens' => collect([$pathName])
                    ->merge($ownedSkills)
                    ->merge($gapSkills)
                    ->values()
                    ->all(),
                'ordered_tokens' => [],
                'allowed_percentages' => [],
            ],
        ];
    }

    private function learningFormattingContext(string $intent, array $data): array
    {
        $priorities = collect($data['learning_priorities'] ?? [])
            ->take(3)
            ->map(function (array $item): array {
                $marketScore = $item['market']['demand_score'] ?? null;

                return [
                    'skill_name' => (string) ($item['skill_name'] ?? ''),
                    'current_level' => $this->number($item['current_level'] ?? 0),
                    'target_level' => $this->number($item['target_level'] ?? 0),
                    'market_demand_percentage' => $marketScore !== null
                        ? $this->number($marketScore)
                        : null,
                    'reason' => $marketScore !== null
                        ? 'market_demand'
                        : 'assessment_gap',
                ];
            })
            ->filter(fn (array $item): bool => $item['skill_name'] !== '')
            ->values();

        if ($priorities->isEmpty()) {
            return $this->emptyFormattingContext();
        }

        $percentages = $priorities
            ->pluck('market_demand_percentage')
            ->filter(fn (mixed $value): bool => $value !== null)
            ->values();

        return [
            'facts' => [
                'intent' => $intent,
                'learning_priorities' => $priorities->all(),
            ],
            'guard' => [
                'required_tokens' => $priorities
                    ->flatMap(function (array $item): array {
                        $tokens = [
                            $item['skill_name'],
                            $item['current_level'],
                            $item['target_level'],
                        ];

                        if ($item['market_demand_percentage'] !== null) {
                            $tokens[] = "{$item['market_demand_percentage']}%";
                        }

                        return $tokens;
                    })
                    ->values()
                    ->all(),
                'ordered_tokens' => $priorities->pluck('skill_name')->all(),
                'allowed_percentages' => $percentages->all(),
            ],
        ];
    }

    private function summaryFormattingContext(array $data): array
    {
        $pathName = trim((string) ($data['assessment']['career_path_name'] ?? ''));
        $skillsCount = count($data['registered_skills'] ?? []);
        $gapSkills = collect($data['skill_gaps'] ?? [])
            ->take(3)
            ->pluck('skill_name')
            ->filter()
            ->map(fn (mixed $name): string => (string) $name)
            ->values();
        $marketSkills = collect($data['market']['top_skills'] ?? [])
            ->take(3)
            ->pluck('skill_name')
            ->filter()
            ->map(fn (mixed $name): string => (string) $name)
            ->values();

        if ($pathName === '' && $skillsCount === 0 && $gapSkills->isEmpty() && $marketSkills->isEmpty()) {
            return $this->emptyFormattingContext();
        }

        return [
            'facts' => [
                'intent' => 'summary',
                'career_path' => $pathName !== '' ? $pathName : null,
                'registered_skills_count' => $skillsCount,
                'highest_skill_gaps' => $gapSkills->all(),
                'top_market_skills' => $marketSkills->all(),
            ],
            'guard' => [
                'required_tokens' => collect([
                    $pathName !== '' ? $pathName : null,
                    (string) $skillsCount,
                ])
                    ->filter(
                        fn (mixed $token): bool => $token !== null && $token !== '',
                    )
                    ->merge($gapSkills)
                    ->merge($marketSkills)
                    ->values()
                    ->all(),
                'ordered_tokens' => [],
                'allowed_percentages' => [],
            ],
        ];
    }

    private function emptyFormattingContext(): array
    {
        return [
            'facts' => [],
            'guard' => [
                'required_tokens' => [],
                'ordered_tokens' => [],
                'allowed_percentages' => [],
            ],
        ];
    }

    private function storeSuccessfulAnswer(
        ChatbotConversation $conversation,
        ChatbotMessage $userMessage,
        string $language,
        string $content,
    ): ChatbotMessage {
        return DB::transaction(function () use (
            $conversation,
            $userMessage,
            $language,
            $content,
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
                'actions' => null,
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
            ? 'The skills and market analysis is temporarily unavailable. Please try again.'
            : 'تعذر جلب تحليل المهارات وسوق العمل مؤقتًا. حاول مرة أخرى.';

        return DB::transaction(function () use (
            $conversation,
            $userMessage,
            $language,
            $content,
        ): ChatbotMessage {
            $userMessage->update([
                'status' => ChatbotMessage::STATUS_FAILED,
                'error_code' => 'SKILLS_MARKET_ANALYSIS_FAILED',
            ]);

            $assistantMessage = $conversation->messages()->create([
                'client_message_id' => null,
                'role' => ChatbotMessage::ROLE_ASSISTANT,
                'content' => $content,
                'language' => $language,
                'status' => ChatbotMessage::STATUS_FAILED,
                'actions' => null,
                'error_code' => 'SKILLS_MARKET_ANALYSIS_FAILED',
            ]);

            $conversation->update([
                'last_message_at' => $assistantMessage->created_at,
            ]);

            return $assistantMessage;
        });
    }

    private function number(mixed $value): string
    {
        $number = (float) $value;

        return fmod($number, 1.0) === 0.0
            ? (string) (int) $number
            : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
