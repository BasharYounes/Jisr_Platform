<?php

namespace App\Services\Assessment;

use App\Models\AssessmentSkillSession;
use App\Models\QuestionBank;

class AssessmentInsightService
{
    public function buildForSkillSession(AssessmentSkillSession $skillSession, array $finalResult = []): array
    {
        $finalLevel = $skillSession->FinalLevel !== null
            ? (float) $skillSession->FinalLevel
            : null;

        $confidenceScore = $skillSession->ConfidenceScore !== null
            ? (float) $skillSession->ConfidenceScore
            : null;

        $testedTopics = $finalResult['tested_topics'] ?? [];

        $availableTopics = $this->availableTopicsForSkill($skillSession);

        $improvementTopics = array_values(array_diff($availableTopics, $testedTopics));

        return [
            'level_label' => $this->levelLabel($finalLevel),
            'confidence_label' => $this->confidenceLabel($confidenceScore),
            'coverage_label' => $this->coverageLabel($finalResult['topic_coverage_ratio'] ?? null),
            'strength_topics' => $testedTopics,
            'improvement_topics' => $improvementTopics,
            'summary_message' => $this->summaryMessage(
                finalLevel: $finalLevel,
                confidenceScore: $confidenceScore,
                topicCoverageRatio: $finalResult['topic_coverage_ratio'] ?? null,
                testedTopics: $testedTopics,
                improvementTopics: $improvementTopics
            ),
        ];
    }

    private function availableTopicsForSkill(AssessmentSkillSession $skillSession): array
    {
        return QuestionBank::query()
            ->where('SkillID', $skillSession->SkillID)
            ->where('IsActive', true)
            ->whereNotNull('Topic')
            ->distinct()
            ->pluck('Topic')
            ->filter()
            ->values()
            ->all();
    }

    private function levelLabel(?float $level): string
    {
        if ($level === null) {
            return 'غير محدد';
        }

        return match (true) {
            $level < 2.0 => 'مبتدئ',
            $level < 3.0 => 'مبتدئ متقدم',
            $level < 4.0 => 'متوسط',
            $level < 4.7 => 'متقدم',
            default => 'متقدم جدًا',
        };
    }

    private function confidenceLabel(?float $confidence): string
    {
        if ($confidence === null) {
            return 'غير محددة';
        }

        return match (true) {
            $confidence < 0.40 => 'منخفضة',
            $confidence < 0.70 => 'متوسطة',
            $confidence < 0.85 => 'جيدة',
            default => 'عالية',
        };
    }

    private function coverageLabel(?float $coverage): string
    {
        if ($coverage === null) {
            return 'غير محسوبة';
        }

        return match (true) {
            $coverage < 0.34 => 'تغطية محدودة',
            $coverage < 0.67 => 'تغطية جزئية',
            default => 'تغطية جيدة',
        };
    }

    private function summaryMessage(
        ?float $finalLevel,
        ?float $confidenceScore,
        ?float $topicCoverageRatio,
        array $testedTopics,
        array $improvementTopics
    ): string {
        $levelLabel = $this->levelLabel($finalLevel);
        $confidenceLabel = $this->confidenceLabel($confidenceScore);
        $coverageLabel = $this->coverageLabel($topicCoverageRatio);

        if ($finalLevel === null) {
            return 'لم يتم تحديد مستوى هذه المهارة بعد بسبب عدم اكتمال بيانات التقييم.';
        }

        if (empty($testedTopics)) {
            return "مستواك في هذه المهارة هو {$levelLabel}، لكن لم يتم تسجيل محاور فرعية كافية لتفسير النتيجة بدقة.";
        }

        if (! empty($improvementTopics)) {
            return "مستواك في هذه المهارة هو {$levelLabel}، وثقة التقييم {$confidenceLabel}. تغطية محاور المهارة: {$coverageLabel}. يُنصح بتعزيز المحاور غير المغطاة لتحسين موثوقية التقييم.";
        }

        return "مستواك في هذه المهارة هو {$levelLabel}، وثقة التقييم {$confidenceLabel}. تغطية محاور المهارة جيدة، والنتيجة تمثل أداءك في عدة محاور من المهارة.";
    }
}
