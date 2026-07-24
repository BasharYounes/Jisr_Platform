<?php

namespace App\Services\MarketAnalysis;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarketSkillDemandContextService
{
    public function getForSkills(int $careerPathId, array $skillIds): array
    {
        $skillIds = collect($skillIds)
            ->filter()
            ->map(fn ($skillId) => (int) $skillId)
            ->unique()
            ->values();

        if ($careerPathId <= 0 || $skillIds->isEmpty()) {
            return [];
        }

        $latestSnapshotDate = DB::table('market_trends')
            ->where('career_path_id', $careerPathId)
            ->max('analyzed_date');

        if ($latestSnapshotDate === null) {
            return $this->buildUnavailableContexts($careerPathId, $skillIds);
        }

        $sampleSize = DB::table('market_job_postings')
            ->where('career_path_id', $careerPathId)
            ->where('status', 'active')
            ->count();

        $careerPathName = DB::table('career_paths')
            ->where('CareerPathID', $careerPathId)
            ->value('Name') ?? 'هذا المسار';

        $trends = DB::table('market_trends')
            ->join('skills', 'skills.id', '=', 'market_trends.skill_id')
            ->where('market_trends.career_path_id', $careerPathId)
            ->where('market_trends.analyzed_date', $latestSnapshotDate)
            ->whereIn('market_trends.skill_id', $skillIds)
            ->select([
                'market_trends.skill_id',
                'skills.name as skill_name',
                'market_trends.demand_score',
                'market_trends.trend_direction',
                'market_trends.source_job_count',
                'market_trends.analyzed_date',
            ])
            ->get()
            ->keyBy('skill_id');

        return $skillIds
            ->mapWithKeys(function (int $skillId) use ($trends, $careerPathName, $sampleSize) {
                $trend = $trends->get($skillId);

                if ($trend === null) {
                    return [
                        $skillId => $this->buildUnavailableContext($careerPathName),
                    ];
                }

                return [
                    $skillId => $this->buildAvailableContext(
                        skillName: (string) $trend->skill_name,
                        careerPathName: $careerPathName,
                        demandScore: (float) $trend->demand_score,
                        trendDirection: (string) $trend->trend_direction,
                        sourceJobCount: (int) $trend->source_job_count,
                        sampleSize: (int) $sampleSize,
                        analyzedDate: (string) $trend->analyzed_date
                    ),
                ];
            })
            ->toArray();
    }

    private function buildUnavailableContexts(int $careerPathId, Collection $skillIds): array
    {
        $careerPathName = DB::table('career_paths')
            ->where('CareerPathID', $careerPathId)
            ->value('Name') ?? 'هذا المسار';

        return $skillIds
            ->mapWithKeys(fn (int $skillId) => [
                $skillId => $this->buildUnavailableContext($careerPathName),
            ])
            ->toArray();
    }

    private function buildAvailableContext(
        string $skillName,
        string $careerPathName,
        float $demandScore,
        string $trendDirection,
        int $sourceJobCount,
        int $sampleSize,
        string $analyzedDate
    ): array {
        $demandLevel = $this->resolveDemandLevel($demandScore);

        return [
            'available' => true,

            'demand_score' => round($demandScore, 2),
            'demand_level' => $demandLevel,
            'trend_direction' => $trendDirection,
            'source_job_count' => $sourceJobCount,
            'sample_size' => $sampleSize,
            'analyzed_date' => $analyzedDate,

            'student_message' => $this->buildStudentMessage(
                skillName: $skillName,
                careerPathName: $careerPathName,
                demandScore: $demandScore,
                demandLevel: $demandLevel,
                sourceJobCount: $sourceJobCount,
                sampleSize: $sampleSize
            ),

            'labels' => [
                'demand_level' => $this->demandLevelLabel($demandLevel),
                'trend_direction' => $this->trendDirectionLabel($trendDirection),
                'learning_priority' => $this->learningPriorityLabel($demandLevel),
            ],

            'explanations' => [
                'demand_score' => "تعني أن هذه المهارة ظهرت في {$demandScore}% من إعلانات الوظائف التي تم تحليلها لهذا المسار.",
                'source_job_count' => "تعني أن هذه المهارة ظهرت في {$sourceJobCount} من أصل {$sampleSize} إعلانات وظائف تم تحليلها.",
                'demand_level' => $this->demandLevelExplanation($demandLevel),
                'trend_direction' => $this->trendDirectionExplanation($trendDirection),
            ],
        ];
    }

    private function buildUnavailableContext(string $careerPathName): array
    {
        return [
            'available' => false,
            'student_message' => "لا توجد حالياً بيانات سوق عمل كافية لهذه المهارة ضمن مسار {$careerPathName}. لذلك سيتم تحديد أولوية التعلم بناءً على نتيجة تقييمك فقط.",
            'labels' => [
                'demand_level' => 'غير متوفر',
                'trend_direction' => 'غير متوفر',
                'learning_priority' => 'تعتمد على نتيجة التقييم',
            ],
            'explanations' => [
                'market_data' => 'لم يتم العثور على تحليل سوق عمل حديث لهذه المهارة ضمن هذا المسار.',
            ],
        ];
    }

    private function buildStudentMessage(
        string $skillName,
        string $careerPathName,
        float $demandScore,
        string $demandLevel,
        int $sourceJobCount,
        int $sampleSize
    ): string {
        $priorityText = match ($demandLevel) {
            'core' => 'لذلك تعتبر من المهارات الأساسية التي يُنصح بإعطائها أولوية عالية في خطة التعلم.',
            'important' => 'لذلك تعتبر مهارة مهمة ويُفضّل تحسينها بعد المهارات الأساسية.',
            default => 'لذلك تعتبر مهارة داعمة، ويمكن تحسينها بعد التركيز على المهارات الأعلى طلباً.',
        };

        return "مهارة {$skillName} مطلوبة في سوق العمل لمسار {$careerPathName}، حيث ظهرت في {$sourceJobCount} من أصل {$sampleSize} إعلانات وظائف تم تحليلها بنسبة طلب {$demandScore}%. {$priorityText}";
    }

    private function resolveDemandLevel(float $demandScore): string
    {
        if ($demandScore >= 60) {
            return 'core';
        }

        if ($demandScore >= 30) {
            return 'important';
        }

        return 'supporting';
    }

    private function demandLevelLabel(string $demandLevel): string
    {
        return match ($demandLevel) {
            'core' => 'مهارة أساسية',
            'important' => 'مهارة مهمة',
            default => 'مهارة داعمة',
        };
    }

    private function learningPriorityLabel(string $demandLevel): string
    {
        return match ($demandLevel) {
            'core' => 'أولوية عالية',
            'important' => 'أولوية متوسطة',
            default => 'أولوية منخفضة',
        };
    }

    private function demandLevelExplanation(string $demandLevel): string
    {
        return match ($demandLevel) {
            'core' => 'تعني أن هذه المهارة مطلوبة في نسبة عالية من الوظائف، لذلك تعتبر أساسية لهذا المسار.',
            'important' => 'تعني أن هذه المهارة مطلوبة بشكل جيد، لكنها ليست الأعلى مقارنة بالمهارات الأساسية.',
            default => 'تعني أن هذه المهارة تظهر في بعض الوظائف، لكنها ليست من أكثر المهارات طلباً حالياً.',
        };
    }

    private function trendDirectionLabel(string $trendDirection): string
    {
        return match ($trendDirection) {
            'rising' => 'الطلب عليها يرتفع',
            'stable' => 'الطلب عليها مستقر',
            'falling' => 'الطلب عليها ينخفض',
            'new' => 'بيانات جديدة',
            default => 'غير معروف',
        };
    }

    private function trendDirectionExplanation(string $trendDirection): string
    {
        return match ($trendDirection) {
            'rising' => 'تعني أن الطلب على هذه المهارة أصبح أعلى مقارنة بالتحليل السابق.',
            'stable' => 'تعني أن الطلب على هذه المهارة مستقر تقريباً مقارنة بالتحليل السابق.',
            'falling' => 'تعني أن الطلب على هذه المهارة انخفض مقارنة بالتحليل السابق.',
            'new' => 'تعني أن هذه أول نتيجة تحليل متوفرة لهذه المهارة، لذلك لا توجد مقارنة سابقة بعد.',
            default => 'لا توجد معلومات كافية لتفسير اتجاه الطلب على هذه المهارة.',
        };
    }
}
