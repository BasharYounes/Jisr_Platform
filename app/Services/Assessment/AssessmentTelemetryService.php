<?php

namespace App\Services\Assessment;

use App\Models\AssessmentEvent;
use Throwable;

class AssessmentTelemetryService
{
    public function record(array $data): void
    {
        try {
            AssessmentEvent::create([
                'assessment_session_id' => $data['assessment_session_id'] ?? null,
                'assessment_skill_session_id' => $data['assessment_skill_session_id'] ?? null,
                'assessment_question_attempt_id' => $data['assessment_question_attempt_id'] ?? null,
                'question_id' => $data['question_id'] ?? null,
                'event_type' => $data['event_type'],
                'level_before' => $data['level_before'] ?? null,
                'level_after' => $data['level_after'] ?? null,
                'normalized_score' => $data['normalized_score'] ?? null,
                'confidence_score' => $data['confidence_score'] ?? null,
                'payload' => $data['payload'] ?? null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            logger()->warning('Assessment telemetry failed', [
                'error' => $e->getMessage(),
                'event_type' => $data['event_type'] ?? null,
            ]);
        }
    }
}
