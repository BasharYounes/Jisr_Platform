<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestionAttempt;
use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\QuestionBank;
use App\Models\Skill;
use App\Models\User;
use App\Services\Assessment\AssessmentCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentCompletionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AssessmentCompletionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AssessmentCompletionService::class);
    }

    public function test_skill_session_does_not_complete_before_minimum_questions(): void
    {
        $skillSession = $this->createSkillSession();

        $this->createEvaluatedAttempt($skillSession, score: 0.90, questionLevel: 3);
        $this->createEvaluatedAttempt($skillSession, score: 0.88, questionLevel: 3);
        $this->createEvaluatedAttempt($skillSession, score: 0.85, questionLevel: 3);
        $this->createEvaluatedAttempt($skillSession, score: 0.80, questionLevel: 3);

        $updatedSkillSession = $this->service
            ->completeSkillSessionIfEligible($skillSession);

        $this->assertNotEquals('completed', $updatedSkillSession->Status);
        $this->assertNull($updatedSkillSession->FinalLevel);
        $this->assertNull($updatedSkillSession->CompletedAt);
    }

    public function test_skill_session_completes_when_confidence_is_high_after_minimum_questions(): void
    {
        $skillSession = $this->createSkillSession();

        $this->createEvaluatedAttempt($skillSession, score: 0.82, questionLevel: 3);
        $this->createEvaluatedAttempt($skillSession, score: 0.84, questionLevel: 3);
        $this->createEvaluatedAttempt($skillSession, score: 0.81, questionLevel: 4);
        $this->createEvaluatedAttempt($skillSession, score: 0.83, questionLevel: 4);
        $this->createEvaluatedAttempt($skillSession, score: 0.85, questionLevel: 5);

        $updatedSkillSession = $this->service
            ->completeSkillSessionIfEligible($skillSession);

        $this->assertEquals('completed', $updatedSkillSession->Status);
        $this->assertNotNull($updatedSkillSession->FinalLevel);
        $this->assertNotNull($updatedSkillSession->ConfidenceScore);
        $this->assertNotNull($updatedSkillSession->CompletedAt);
    }

    public function test_skill_session_completes_at_maximum_questions_even_if_confidence_is_not_high(): void
    {
        $skillSession = $this->createSkillSession();

        $scores = [0.50, 0.55, 0.45, 0.60, 0.40, 0.65, 0.35, 0.52, 0.59, 0.43];

        foreach ($scores as $index => $score) {
            $this->createEvaluatedAttempt(
                skillSession: $skillSession,
                score: $score,
                questionLevel: ($index % 5) + 1
            );
        }

        $updatedSkillSession = $this->service
            ->completeSkillSessionIfEligible($skillSession);

        $this->assertEquals('completed', $updatedSkillSession->Status);
        $this->assertNotNull($updatedSkillSession->FinalLevel);
        $this->assertNotNull($updatedSkillSession->ConfidenceScore);
        $this->assertNotNull($updatedSkillSession->CompletedAt);
    }

    public function test_should_stop_asking_returns_true_when_skill_session_is_completed(): void
    {
        $skillSession = $this->createSkillSession([
            'Status' => 'completed',
            'FinalLevel' => 3.50,
            'ConfidenceScore' => 0.80,
            'CompletedAt' => now(),
        ]);

        $shouldStop = $this->service->shouldStopAsking($skillSession);

        $this->assertTrue($shouldStop);
    }

    public function test_completion_reason_is_not_completed_before_minimum_questions(): void
    {
        $skillSession = $this->createSkillSession();

        $this->createEvaluatedAttempt($skillSession, score: 0.90, questionLevel: 3);
        $this->createEvaluatedAttempt($skillSession, score: 0.88, questionLevel: 3);
        $this->createEvaluatedAttempt($skillSession, score: 0.91, questionLevel: 4);
        $this->createEvaluatedAttempt($skillSession, score: 0.89, questionLevel: 4);

        $reason = $this->service->resolveCompletionReason($skillSession);

        $this->assertEquals('not_completed', $reason);
    }

    public function test_completion_reason_is_confidence_threshold_reached(): void
    {
        $skillSession = $this->createSkillSession();

        $this->createEvaluatedAttempt($skillSession, score: 0.82, questionLevel: 3);
        $this->createEvaluatedAttempt($skillSession, score: 0.84, questionLevel: 3);
        $this->createEvaluatedAttempt($skillSession, score: 0.81, questionLevel: 4);
        $this->createEvaluatedAttempt($skillSession, score: 0.83, questionLevel: 4);
        $this->createEvaluatedAttempt($skillSession, score: 0.85, questionLevel: 5);

        $reason = $this->service->resolveCompletionReason($skillSession);

        $this->assertEquals('confidence_threshold_reached', $reason);
    }

    public function test_completion_reason_is_max_questions_reached(): void
    {
        $skillSession = $this->createSkillSession();

        $scores = [0.50, 0.55, 0.45, 0.60, 0.40, 0.65, 0.35, 0.70, 0.48, 0.52];

        foreach ($scores as $index => $score) {
            $this->createEvaluatedAttempt(
                skillSession: $skillSession,
                score: $score,
                questionLevel: ($index % 5) + 1
            );
        }

        $reason = $this->service->resolveCompletionReason($skillSession);

        $this->assertEquals('max_questions_reached', $reason);
    }

    public function test_completion_reason_is_already_completed(): void
    {
        $skillSession = $this->createSkillSession([
            'Status' => 'completed',
            'FinalLevel' => 3.50,
            'ConfidenceScore' => 0.80,
            'CompletedAt' => now(),
        ]);

        $reason = $this->service->resolveCompletionReason($skillSession);

        $this->assertEquals('already_completed', $reason);
    }

    public function test_topic_adjusted_confidence_does_not_penalize_when_only_one_topic_is_available(): void
    {
        $skillSession = $this->createSkillSession();

        for ($i = 0; $i < 5; $i++) {
            $this->createEvaluatedAttempt(
                skillSession: $skillSession,
                score: 0.85,
                questionLevel: 3,
                topic: 'Routing'
            );
        }

        $attempts = $this->extractAttemptsForTest($skillSession);

        $baseConfidence = app(\App\Services\Assessment\LevelEstimationService::class)
            ->calculateConfidenceFromAttempts($attempts);

        $adjustedConfidence = $this->service
            ->calculateTopicAdjustedConfidence($skillSession, $attempts);

        $this->assertEquals($baseConfidence, $adjustedConfidence);
    }

    public function test_topic_adjusted_confidence_penalizes_low_topic_coverage(): void
    {
        $skillSession = $this->createSkillSession();

        $availableTopics = ['Routing', 'Eloquent', 'Validation', 'Middleware', 'Testing'];

        foreach ($availableTopics as $topic) {
            $this->createQuestion(
                skillId: $skillSession->SkillID,
                level: 3,
                topic: $topic
            );
        }

        for ($i = 0; $i < 5; $i++) {
            $this->createEvaluatedAttempt(
                skillSession: $skillSession,
                score: 0.85,
                questionLevel: 3,
                topic: 'Routing'
            );
        }

        $attempts = $this->extractAttemptsForTest($skillSession);

        $baseConfidence = app(\App\Services\Assessment\LevelEstimationService::class)
            ->calculateConfidenceFromAttempts($attempts);

        $adjustedConfidence = $this->service
            ->calculateTopicAdjustedConfidence($skillSession, $attempts);

        $this->assertLessThan($baseConfidence, $adjustedConfidence);
        $this->assertGreaterThanOrEqual(round($baseConfidence * 0.85, 2), $adjustedConfidence);
    }

    private function createQuestion(
        int $skillId,
        int $level,
        ?string $topic = null
    ): QuestionBank {
        return QuestionBank::query()->create([
            'SkillID' => $skillId,
            'CareerPathID' => null,
            'Level' => $level,
            'QuestionType' => 'open_text',
            'Topic' => $topic,
            'QuestionText' => 'Explain this concept.',
            'ExpectedAnswerType' => 'text',
            'DifficultyWeight' => 1.0,
            'IsActive' => true,
        ]);
    }

    private function extractAttemptsForTest(AssessmentSkillSession $skillSession): array
    {
        $skillSession->load('questionAttempts.questionBank');

        return $skillSession->questionAttempts
            ->sortBy('AskedAt')
            ->filter(fn ($attempt) => $attempt->NormalizedScore !== null && $attempt->NormalizedScore !== '')
            ->map(function ($attempt) {
                return [
                    'score' => (float) $attempt->NormalizedScore,
                    'question_level' => (float) (
                        $attempt->QuestionLevel
                        ?? $attempt->questionBank?->Level
                        ?? 1
                    ),
                    'difficulty_weight' => (float) (
                        $attempt->questionBank?->DifficultyWeight
                        ?? 1.0
                    ),
                ];
            })
            ->values()
            ->all();
    }

    private function createSkillSession(array $overrides = []): AssessmentSkillSession
    {
        $skill = Skill::query()->create([
            'name' => 'Laravel ' . uniqid(),
            'category' => 'Backend',
            'normalized_name' => 'laravel_' . uniqid(),
        ]);

        $user = User::query()->create([
            'name' => 'Test Student',
            'email' => 'student_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $careerPath = CareerPath::query()->create([
            'Name' => 'Backend Developer ' . uniqid(),
            'Description' => 'Backend development path',
        ]);

        $assessmentSession = AssessmentSession::query()->create([
            'UserID' => $user->id,
            'CareerPathID' => $careerPath->CareerPathID,
            'Status' => 'in_progress',
            'StartedAt' => now(),
        ]);

        return AssessmentSkillSession::query()->create(array_merge([
            'AssessmentSessionID' => $assessmentSession->AssessmentSessionID,
            'SkillID' => $skill->id,
            'InitialLevel' => 3.0,
            'CurrentEstimatedLevel' => 3.0,
            'FinalLevel' => null,
            'ConfidenceScore' => null,
            'QuestionCount' => 0,
            'Status' => 'in_progress',
            'CompletedAt' => null,
        ], $overrides));
    }

    private function createEvaluatedAttempt(
        AssessmentSkillSession $skillSession,
        float $score,
        int $questionLevel,
        ?string $topic = null,
        float $difficultyWeight = 1.0
    ): AssessmentQuestionAttempt {
        $question = QuestionBank::query()->create([
            'SkillID' => $skillSession->SkillID,
            'CareerPathID' => null,
            'Level' => $questionLevel,
            'QuestionType' => 'open_text',
            'Topic' => $topic,
            'QuestionText' => 'Explain this concept.',
            'ExpectedAnswerType' => 'text',
            'DifficultyWeight' => $difficultyWeight,
            'IsActive' => true,
        ]);

        return AssessmentQuestionAttempt::query()->create([
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
            'QuestionID' => $question->QuestionID,
            'QuestionLevel' => $questionLevel,
            'AskedAt' => now(),
            'AnsweredAt' => now(),
            'LlmEvaluationStatus' => 'completed',
            'RawScore' => $score * 100,
            'NormalizedScore' => $score,
            'FeedbackText' => 'Test feedback.',
            'EvaluationJson' => [
                'source' => 'test',
            ],
        ]);
    }
}
