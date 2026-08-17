<?php

namespace App\Services\Chatbot;

use App\Models\Opportunity;
use Illuminate\Support\Collection;

class ChatbotOpportunityMatchPresenter
{
    /**
     * @param  Collection<int, Opportunity>  $opportunities
     * @return array{content: string, actions: array<int, array<string, mixed>>}
     */
    public function present(Collection $opportunities, string $language): array
    {
        $language = $language === 'en' ? 'en' : 'ar';

        if ($opportunities->isEmpty()) {
            return [
                'content' => $language === 'en'
                    ? 'I could not find a currently published and active opportunity that safely matches your registered skills. Add or update your skills, then try again.'
                    : 'لم أجد حاليًا فرصة منشورة ونشطة تناسب مهاراتك المسجلة بشكل آمن. أضف مهاراتك أو حدّث مستوياتها ثم حاول مرة أخرى.',
                'actions' => [],
            ];
        }

        $lines = [];
        $actions = [];

        foreach ($opportunities->values() as $index => $opportunity) {
            $match = is_array($opportunity->match_data ?? null)
                ? $opportunity->match_data
                : [];

            $title = (string) ($opportunity->title ?: ($language === 'en' ? 'Opportunity' : 'فرصة'));
            $score = $this->number($match['score'] ?? 0);
            $type = $this->typeLabel($opportunity->type, $language);
            $location = trim((string) ($opportunity->location ?? ''));

            $fullMatches = collect($match['matched_skills'] ?? [])
                ->where('match_type', 'full')
                ->pluck('name')
                ->filter()
                ->values();

            $partialMatches = collect($match['matched_skills'] ?? [])
                ->where('match_type', 'partial')
                ->pluck('name')
                ->filter()
                ->values();

            $missingSkills = collect($match['missing_skills'] ?? [])
                ->pluck('name')
                ->filter()
                ->values();

            $details = $this->buildDetails(
                language: $language,
                fullMatches: $fullMatches,
                partialMatches: $partialMatches,
                missingSkills: $missingSkills,
            );

            $meta = collect([$type, $location !== '' ? $location : null])
                ->filter()
                ->implode('، ');

            $number = $index + 1;

            if ($language === 'en') {
                $lines[] = "{$number}) {$title} — match {$score}%"
                    .($meta !== '' ? " ({$meta})" : '')
                    .". {$details}";
            } else {
                $lines[] = "{$number}) {$title} — نسبة المطابقة {$score}%"
                    .($meta !== '' ? " ({$meta})" : '')
                    .". {$details}";
            }

            $actions[] = [
                'type' => 'open_opportunity',
                'label' => $language === 'en'
                    ? "View {$title}"
                    : "عرض فرصة {$title}",
                'opportunity_id' => (int) $opportunity->id,
            ];
        }

        $intro = $language === 'en'
            ? 'The best currently suitable opportunities are:'
            : 'أفضل الفرص المناسبة لك حاليًا هي:';

        $footer = $language === 'en'
            ? 'The ranking is based on your registered skill levels, the required skill levels, skill weights, and mandatory-skill eligibility.'
            : 'تم الترتيب اعتمادًا على مستويات مهاراتك، والمستويات المطلوبة، وأوزان المهارات، وعدم وجود مهارة إلزامية ناقصة.';

        return [
            'content' => $intro."\n".implode("\n", $lines)."\n".$footer,
            'actions' => $actions,
        ];
    }

    private function buildDetails(
        string $language,
        Collection $fullMatches,
        Collection $partialMatches,
        Collection $missingSkills,
    ): string {
        $parts = [];

        if ($fullMatches->isNotEmpty()) {
            $parts[] = $language === 'en'
                ? 'You fully match: '.$fullMatches->implode(', ')
                : 'تمتلك بالمستوى المطلوب: '.$fullMatches->implode('، ');
        }

        if ($partialMatches->isNotEmpty()) {
            $parts[] = $language === 'en'
                ? 'You have these skills below the required level: '.$partialMatches->implode(', ')
                : 'تمتلك بمستوى أقل من المطلوب: '.$partialMatches->implode('، ');
        }

        if ($missingSkills->isNotEmpty()) {
            $parts[] = $language === 'en'
                ? 'Skills to develop: '.$missingSkills->implode(', ')
                : 'مهارات تحتاج إلى تطويرها: '.$missingSkills->implode('، ');
        }

        if ($parts === []) {
            return $language === 'en'
                ? 'The opportunity has a valid calculated match with your profile.'
                : 'لديك مطابقة محسوبة ومقبولة مع متطلبات هذه الفرصة.';
        }

        return implode('. ', $parts).'.';
    }

    private function typeLabel(?string $type, string $language): ?string
    {
        return match ($type) {
            'job' => $language === 'en' ? 'Job' : 'وظيفة',
            'internship' => $language === 'en' ? 'Internship' : 'تدريب',
            default => null,
        };
    }

    private function number(mixed $value): string
    {
        $number = (float) $value;

        return fmod($number, 1.0) === 0.0
            ? (string) (int) $number
            : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
