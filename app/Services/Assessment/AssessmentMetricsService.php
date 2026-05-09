<?php

namespace App\Services\Assessment;

use App\Models\AssessmentEvent;
use Illuminate\Support\Facades\DB;

class AssessmentMetricsService
{
    public function questionMetrics(): array
    {
        return AssessmentEvent::query()
            ->where('event_type', 'answer_evaluated')
            ->whereNotNull('question_id')
            ->select(
                'question_id',
                DB::raw('COUNT(*) as attempt_count'),
                DB::raw('AVG(normalized_score) as average_score'),
                DB::raw('AVG(confidence_score) as average_ai_confidence'),
                DB::raw('MIN(normalized_score) as min_score'),
                DB::raw('MAX(normalized_score) as max_score'),
                DB::raw('AVG(level_after - level_before) as average_level_shift')
            )
            ->groupBy('question_id')
            ->orderByDesc('attempt_count')
            ->get()
            ->toArray();
    }

    public function lowConfidenceEvaluations(float $threshold = 0.60): array
    {
        return AssessmentEvent::query()
            ->where('event_type', 'answer_evaluated')
            ->whereNotNull('confidence_score')
            ->where('confidence_score', '<', $threshold)
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->toArray();
    }

    public function completedSkillSessionsMetrics(): array
    {
        return AssessmentEvent::query()
            ->where('event_type', 'skill_session_completed')
            ->select(
                DB::raw('COUNT(*) as completed_sessions'),
                DB::raw('AVG(level_after) as average_final_level'),
                DB::raw('AVG(confidence_score) as average_confidence')
            )
            ->first()
            ->toArray();
    }
}
