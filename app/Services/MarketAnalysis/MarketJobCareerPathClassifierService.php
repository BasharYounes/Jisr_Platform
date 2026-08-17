<?php

namespace App\Services\MarketAnalysis;

use App\Models\MarketJobPosting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MarketJobCareerPathClassifierService
{
    public function __construct(
        private readonly MarketTextNormalizer $textNormalizer,
    ) {}

    /**
     * Classify one job posting and persist the result.
     *
     * The returned score is a weighted rule score,
     * not a statistical probability.
     *
     * @return array<string, mixed>
     */
    public function classify(MarketJobPosting $jobPosting): array
    {
        $configuration = config(
            'market_analysis_classifier'
        );

        $pathDefinitions = $configuration['paths'] ?? [];

        if ($pathDefinitions === []) {
            throw new RuntimeException(
                'Market career path classifier configuration is empty.'
            );
        }

        /*
         * نحصل على IDs المسارات الموجودة فعلياً
         * ولا نعتمد على أرقام ثابتة.
         */
        $pathIds = DB::table('career_paths')
            ->whereIn('Name', array_keys($pathDefinitions))
            ->pluck('CareerPathID', 'Name')
            ->map(
                fn ($careerPathId): int => (int) $careerPathId
            )
            ->all();

        if ($pathIds === []) {
            throw new RuntimeException(
                'No supported market career paths were found.'
            );
        }

        $scores = array_fill_keys(
            array_keys($pathIds),
            0.0
        );

        $normalizedTitle = $this->textNormalizer->normalize(
            (string) $jobPosting->title
        );

        $titleEvidence = $this->scoreTitleSignals(
            normalizedTitle: $normalizedTitle,
            pathDefinitions: $pathDefinitions,
            scores: $scores,
        );

        $skillEvidence = $this->scoreExtractedSkills(
            jobPostingId: (int) $jobPosting->id,
            pathIds: $pathIds,
            scores: $scores,
            coreSkillMultiplier: (float) (
                $configuration['core_skill_multiplier'] ?? 2.0
            ),
        );

        $outOfScopeSignals = $this->findMatchingTitleSignals(
            normalizedTitle: $normalizedTitle,
            signals: $configuration[
                'out_of_scope_title_signals'
            ] ?? [],
        );

        $ambiguousTitleSignals = $this->findMatchingTitleSignals(
            normalizedTitle: $normalizedTitle,
            signals: $configuration[
                'ambiguous_title_signals'
            ] ?? [],
        );

        /*
         * ترتيب المسارات من الأعلى إلى الأقل.
         */
        arsort($scores);

        $rankedPathNames = array_keys($scores);

        $topPathName = $rankedPathNames[0] ?? null;
        $secondPathName = $rankedPathNames[1] ?? null;

        $topScore = $topPathName !== null
            ? (float) $scores[$topPathName]
            : 0.0;

        $secondScore = $secondPathName !== null
            ? (float) $scores[$secondPathName]
            : 0.0;

        $margin = $topScore - $secondScore;

        $ratio = $secondScore > 0
            ? $topScore / $secondScore
            : null;

        $thresholds = $configuration['thresholds'] ?? [];

        $minimumScore = (float) (
            $thresholds['minimum_score'] ?? 2.5
        );

        $minimumMargin = (float) (
            $thresholds['minimum_margin'] ?? 1.0
        );

        $minimumRatio = (float) (
            $thresholds['minimum_ratio'] ?? 1.25
        );

        $status = $this->determineStatus(
            topScore: $topScore,
            secondScore: $secondScore,
            margin: $margin,
            ratio: $ratio,
            minimumScore: $minimumScore,
            minimumMargin: $minimumMargin,
            minimumRatio: $minimumRatio,
            hasOutOfScopeSignal: $outOfScopeSignals !== [],
            hasSupportedTitleSignal: $titleEvidence !== [],
            hasAmbiguousTitleSignal: $ambiguousTitleSignals !== [],
        );

        $selectedCareerPathId = (
            $status === 'classified' &&
            $topPathName !== null
        )
            ? $pathIds[$topPathName]
            : null;

        $method = (string) (
            $configuration['method'] ?? 'weighted_rules_v1'
        );

        $roundedScores = collect($scores)
            ->map(
                fn (float $score): float => round($score, 3)
            )
            ->all();

        $evidence = [
            'normalized_title' => $normalizedTitle,

            'title_signals' => $titleEvidence,

            'skills' => $skillEvidence,

            'path_scores' => $roundedScores,

            'ranking' => [
                'top_path' => $topPathName,
                'top_score' => round($topScore, 3),
                'second_path' => $secondPathName,
                'second_score' => round($secondScore, 3),
                'margin' => round($margin, 3),
                'ratio' => $ratio !== null
                    ? round($ratio, 3)
                    : null,
            ],

            'out_of_scope_title_signals' => $outOfScopeSignals,

            'ambiguous_title_signals' => $ambiguousTitleSignals,

            'thresholds' => [
                'minimum_score' => $minimumScore,
                'minimum_margin' => $minimumMargin,
                'minimum_ratio' => $minimumRatio,
            ],
        ];

        /*
         * نستخدم Query Builder حتى لا نعتمد حالياً
         * على وجود JSON cast داخل المودل.
         */
        DB::table('market_job_postings')
            ->where('id', $jobPosting->id)
            ->update([
                'career_path_id' => $selectedCareerPathId,

                'classification_status' => $status,

                'classification_method' => $method,

                'classification_score' => round(
                    $topScore,
                    3
                ),

                'classification_evidence' => json_encode(
                    $evidence,
                    JSON_THROW_ON_ERROR |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                ),

                'classified_at' => now(),

                'updated_at' => now(),
            ]);

        $jobPosting->refresh();

        return [
            'status' => $status,

            'career_path_id' => $selectedCareerPathId,

            'career_path_name' => $status === 'classified'
                    ? $topPathName
                    : null,

            'score' => round($topScore, 3),

            'method' => $method,

            'evidence' => $evidence,
        ];
    }

    /**
     * @param  array<string, array<string, float|int>>  $pathDefinitions
     * @param  array<string, float>  $scores
     * @return array<int, array<string, mixed>>
     */
    private function scoreTitleSignals(
        string $normalizedTitle,
        array $pathDefinitions,
        array &$scores,
    ): array {
        $evidence = [];

        foreach ($pathDefinitions as $pathName => $signals) {
            if (! array_key_exists($pathName, $scores)) {
                continue;
            }

            /*
             * نأخذ أعلى إشارة عنوان فقط لكل مسار.
             * لا نجمع Backend Developer وAPI Developer معاً.
             */
            $bestSignalScore = 0.0;

            foreach ($signals as $signal => $points) {
                $normalizedSignal =
                    $this->textNormalizer->normalize(
                        (string) $signal
                    );

                if (! $this->containsPhrase(
                    $normalizedTitle,
                    $normalizedSignal
                )) {
                    continue;
                }

                $points = (float) $points;

                $evidence[] = [
                    'career_path' => $pathName,
                    'signal' => $signal,
                    'normalized_signal' => $normalizedSignal,
                    'points' => $points,
                ];

                $bestSignalScore = max(
                    $bestSignalScore,
                    $points
                );
            }

            $scores[$pathName] += $bestSignalScore;
        }

        return $evidence;
    }

    /**
     * @param  array<string, int>  $pathIds
     * @param  array<string, float>  $scores
     * @return array<int, array<string, mixed>>
     */
    private function scoreExtractedSkills(
        int $jobPostingId,
        array $pathIds,
        array &$scores,
        float $coreSkillMultiplier,
    ): array {
        $relations = DB::table(
            'market_job_posting_skill_occurrences as occurrence'
        )
            ->join(
                'skills as skill',
                'skill.id',
                '=',
                'occurrence.skill_id'
            )
            ->join(
                'career_path_skills as path_skill',
                'path_skill.SkillID',
                '=',
                'occurrence.skill_id'
            )
            ->join(
                'career_paths as career_path',
                'career_path.CareerPathID',
                '=',
                'path_skill.CareerPathID'
            )
            ->where(
                'occurrence.market_job_posting_id',
                $jobPostingId
            )
            ->whereIn(
                'career_path.CareerPathID',
                array_values($pathIds)
            )
            ->select([
                'skill.id as skill_id',
                'skill.name as skill_name',
                'career_path.Name as career_path_name',
                'path_skill.Weight as weight',
                'path_skill.IsCore as is_core',
            ])
            ->distinct()
            ->get();

        $evidence = [];

        foreach ($relations as $relation) {
            $pathName = (string) (
                $relation->career_path_name
            );

            if (! array_key_exists($pathName, $scores)) {
                continue;
            }

            $weight = (float) $relation->weight;
            $isCore = (bool) $relation->is_core;

            $points = $weight * (
                $isCore
                    ? $coreSkillMultiplier
                    : 1.0
            );

            $scores[$pathName] += $points;

            $evidence[] = [
                'skill_id' => (int) $relation->skill_id,
                'skill_name' => (string) $relation->skill_name,
                'career_path' => $pathName,
                'weight' => $weight,
                'is_core' => $isCore,
                'points' => round($points, 3),
            ];
        }

        return $evidence;
    }

    /**
     * @param  array<int, string>  $signals
     * @return array<int, string>
     */
    private function findMatchingTitleSignals(
        string $normalizedTitle,
        array $signals,
    ): array {
        $matches = [];

        foreach ($signals as $signal) {
            $normalizedSignal =
                $this->textNormalizer->normalize(
                    (string) $signal
                );

            if ($this->containsPhrase(
                $normalizedTitle,
                $normalizedSignal
            )) {
                $matches[] = (string) $signal;
            }
        }

        return array_values(array_unique($matches));
    }

    private function determineStatus(
        float $topScore,
        float $secondScore,
        float $margin,
        ?float $ratio,
        float $minimumScore,
        float $minimumMargin,
        float $minimumRatio,
        bool $hasOutOfScopeSignal,
        bool $hasSupportedTitleSignal,
        bool $hasAmbiguousTitleSignal,
    ): string {

        /*
        * An explicit unsupported job title must not be
        * overridden by generic skills found in the description.
        *
        * Example:
        * Data Science job containing Git, Python and Docker.
        */
        if (
            $hasOutOfScopeSignal &&
            ! $hasSupportedTitleSignal
        ) {
            return 'out_of_scope';
        }
        /*
        * Full-Stack explicitly combines multiple supported paths,
        * so it must not be forced into the highest-scoring path.
        */
        if ($hasAmbiguousTitleSignal) {
            return 'ambiguous';
        }
        /*
         * لا توجد أدلة تقنية.
         */
        if ($topScore <= 0.0) {
            return $hasOutOfScopeSignal
                ? 'out_of_scope'
                : 'insufficient_evidence';
        }

        /*
         * توجد إشارة غير تقنية، لكن الأدلة التقنية
         * لم تصل إلى الحد الأدنى.
         */
        if (
            $topScore < $minimumScore &&
            $hasOutOfScopeSignal
        ) {
            return 'out_of_scope';
        }

        /*
         * توجد أدلة تقنية لكنها ضعيفة.
         */
        if ($topScore < $minimumScore) {
            return 'insufficient_evidence';
        }

        /*
         * المساران الأول والثاني متقاربان.
         */
        if (
            $secondScore > 0.0 &&
            (
                $margin < $minimumMargin ||
                (
                    $ratio !== null &&
                    $ratio < $minimumRatio
                )
            )
        ) {
            return 'ambiguous';
        }

        return 'classified';
    }

    private function containsPhrase(
        string $normalizedText,
        string $normalizedPhrase,
    ): bool {
        if ($normalizedPhrase === '') {
            return false;
        }

        $escapedPhrase = preg_quote(
            $normalizedPhrase,
            '/'
        );

        $pattern =
            '/(?<![\p{L}\p{N}])'.
            $escapedPhrase.
            '(?![\p{L}\p{N}])/u';

        return preg_match(
            $pattern,
            $normalizedText
        ) === 1;
    }
}
