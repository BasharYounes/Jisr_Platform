<?php

namespace App\Services\MarketAnalysis;

use App\Models\MarketJobPosting;
use App\Models\MarketJobPostingSkillOccurrence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketSkillExtractionService
{
    private ?Collection $preparedAliases = null;

    public function __construct(
        private readonly MarketTextNormalizer $textNormalizer,
    ) {}

    /**
     * Extract skills from a market job posting using rule-based alias matching.
     */
    public function extractForJobPosting(MarketJobPosting $jobPosting): Collection
    {
        $rawText = trim($jobPosting->title . "\n" . $jobPosting->description);
        $normalizedText = $this->textNormalizer->normalize($rawText);

        $aliases = $this->loadPreparedSkillAliases();

        $matchesBySkill = [];

        foreach ($aliases as $alias) {
            if (! $this->containsAlias($normalizedText, $alias->normalized_alias)) {
                continue;
            }

            $skillId = (int) $alias->SkillID;

            /*
             * If multiple aliases match the same skill in the same job posting,
             * keep the longest alias because it is usually more specific.
             * Example: REST API is better evidence than API.
             */
            if (
                ! isset($matchesBySkill[$skillId]) ||
                $alias->alias_length > $matchesBySkill[$skillId]['alias_length']
            ) {
                $matchesBySkill[$skillId] = [
                    'market_job_posting_id' => $jobPosting->id,
                    'skill_id' => $skillId,
                    'skill_alias_id' => $alias->SkillAliasID,
                    'matched_text' => $alias->Alias,
                    'language' => $alias->LanguageCode ?: $jobPosting->language,
                    'confidence' => 1.00,
                    'extraction_method' => 'alias_match',
                    'context' => $this->extractContext($rawText, $alias->Alias),
                    'alias_length' => $alias->alias_length,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $records = collect($matchesBySkill)
            ->map(function (array $match) {
                unset($match['alias_length']);

                return $match;
            })
            ->values();

        DB::transaction(function () use ($jobPosting, $records): void {
            MarketJobPostingSkillOccurrence::query()
                ->where('market_job_posting_id', $jobPosting->id)
                ->delete();

            if ($records->isNotEmpty()) {
                MarketJobPostingSkillOccurrence::query()->insert($records->all());
            }
        });

        return $records;
    }

    /**
     * Clears the in-memory alias cache.
     * Useful if aliases are updated inside a long-running process.
     */
    public function clearAliasCache(): void
    {
        $this->preparedAliases = null;
    }

    /**
     * Load and prepare aliases once per service instance.
     */
    private function loadPreparedSkillAliases(): Collection
    {
        if ($this->preparedAliases !== null) {
            return $this->preparedAliases;
        }

        $this->preparedAliases = DB::table('skill_aliases')
            ->join('skills', 'skills.id', '=', 'skill_aliases.SkillID')
            ->select([
                'skill_aliases.SkillAliasID',
                'skill_aliases.SkillID',
                'skill_aliases.Alias',
                'skill_aliases.LanguageCode',
                'skills.name as skill_name',
                'skills.category as skill_category',
            ])
            ->get()
            ->map(function ($alias) {
                $alias->Alias = (string) $alias->Alias;
                $alias->normalized_alias = $this->textNormalizer->normalize(
                    $alias->Alias
                );
                $alias->alias_length = mb_strlen($alias->normalized_alias);

                return $alias;
            })
            ->filter(function ($alias) {
                return ! $this->shouldSkipAlias($alias->normalized_alias);
            })
            ->sortByDesc('alias_length')
            ->values();

        return $this->preparedAliases;
    }

    /**
     * Skip aliases that are too short or too risky to match safely.
     */
    private function shouldSkipAlias(string $normalizedAlias): bool
    {
        if ($normalizedAlias === '') {
            return true;
        }

        /*
         * Avoid high-risk one-letter aliases such as C or R.
         * These create many false positives.
         */
        if (mb_strlen($normalizedAlias) <= 1) {
            return true;
        }

        return false;
    }

    /**
     * Match alias as a full token/phrase, not as part of another word.
     */
    private function containsAlias(string $normalizedText, string $normalizedAlias): bool
    {
        $escapedAlias = preg_quote($normalizedAlias, '/');

        /*
         * Arabic words often attach short prefixes directly to the word,
         * such as: والعمل، بالعمل، للطلاب، كفريق.
         */
        if ($this->containsArabic($normalizedAlias)) {
            $pattern = '/(?<![\p{L}\p{N}])(?:[وفبلك])?'.$escapedAlias.'(?![\p{L}\p{N}])/u';

            return preg_match($pattern, $normalizedText) === 1;
        }

        $pattern = '/(?<![\p{L}\p{N}])'.$escapedAlias.'(?![\p{L}\p{N}])/u';

        return preg_match($pattern, $normalizedText) === 1;
    }

    private function containsArabic(string $text): bool
    {
        return preg_match('/\p{Arabic}/u', $text) === 1;
    }

    /**
     * Extract clean sentence/line context for explainability.
     */
    private function extractContext(string $rawText, string $matchedText): ?string
    {
        $normalizedMatchedText = trim($matchedText);

        if ($normalizedMatchedText === '') {
            return null;
        }

        $parts = preg_split('/[\r\n.!؟?؛;]+/u', $rawText);

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (mb_stripos($part, $normalizedMatchedText) !== false) {
                return $part;
            }
        }

        $position = mb_stripos($rawText, $normalizedMatchedText);

        if ($position === false) {
            return null;
        }

        $start = max(0, $position - 80);
        $length = mb_strlen($normalizedMatchedText) + 160;

        return trim(mb_substr($rawText, $start, $length));
    }
}
