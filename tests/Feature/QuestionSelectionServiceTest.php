<?php

namespace Tests\Feature;

// use App\Models\AssessmentEvent;
use App\Models\AssessmentQuestionAttempt;
use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\QuestionBank;
use App\Models\Skill;
use App\Models\User;
use App\Services\Assessment\QuestionSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuestionSelectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(QuestionSelectionService::class);
    }

    public function test_it_selects_question_from_exact_current_level(): void
    {
        $skillSession = $this->createSkillSession([
            'CurrentEstimatedLevel' => 3.0,
        ]);

        $expectedQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 3
        );

        $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 4
        );

        $selectedQuestion = $this->service->selectNextQuestion($skillSession);

        $this->assertNotNull($selectedQuestion);
        $this->assertEquals($expectedQuestion->QuestionID, $selectedQuestion->QuestionID);

        $this->assertDatabaseHas('assessment_events', [
            'assessment_session_id' => $skillSession->AssessmentSessionID,
            'assessment_skill_session_id' => $skillSession->AssessmentSkillSessionID,
            'question_id' => $selectedQuestion->QuestionID,
            'event_type' => 'question_selected',
        ]);
    }

    public function test_it_does_not_repeat_used_questions(): void
    {
        $skillSession = $this->createSkillSession([
            'CurrentEstimatedLevel' => 3.0,
        ]);

        $usedQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 3
        );

        $unusedQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 3
        );

        AssessmentQuestionAttempt::query()->create([
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
            'QuestionID' => $usedQuestion->QuestionID,
            'QuestionLevel' => 3,
            'AskedAt' => now()->subMinutes(5),
            'AnsweredAt' => now()->subMinutes(4),
            'LlmEvaluationStatus' => 'completed',
            'RawScore' => 80,
            'NormalizedScore' => 0.80,
            'FeedbackText' => 'Test feedback.',
            'EvaluationJson' => ['source' => 'test'],
        ]);

        $selectedQuestion = $this->service->selectNextQuestion($skillSession);

        $this->assertNotNull($selectedQuestion);
        $this->assertEquals($unusedQuestion->QuestionID, $selectedQuestion->QuestionID);
        $this->assertNotEquals($usedQuestion->QuestionID, $selectedQuestion->QuestionID);
    }

    public function test_it_falls_back_to_nearby_level_when_exact_level_is_not_available(): void
    {
        $skillSession = $this->createSkillSession([
            'CurrentEstimatedLevel' => 3.0,
        ]);

        $fallbackQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 4
        );

        $selectedQuestion = $this->service->selectNextQuestion($skillSession);

        $this->assertNotNull($selectedQuestion);
        $this->assertEquals($fallbackQuestion->QuestionID, $selectedQuestion->QuestionID);
        $this->assertEquals(4, $selectedQuestion->Level);
    }

    public function test_it_selects_higher_level_after_strong_answer(): void
    {
        $skillSession = $this->createSkillSession([
            'CurrentEstimatedLevel' => 3.0,
        ]);

        $previousQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 3
        );

        AssessmentQuestionAttempt::query()->create([
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
            'QuestionID' => $previousQuestion->QuestionID,
            'QuestionLevel' => 3,
            'AskedAt' => now()->subMinutes(5),
            'AnsweredAt' => now()->subMinutes(4),
            'LlmEvaluationStatus' => 'completed',
            'RawScore' => 90,
            'NormalizedScore' => 0.90,
            'FeedbackText' => 'Strong answer.',
            'EvaluationJson' => ['source' => 'test'],
        ]);

        $higherLevelQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 4
        );

        $selectedQuestion = $this->service->selectNextQuestion($skillSession);

        $this->assertNotNull($selectedQuestion);
        $this->assertEquals($higherLevelQuestion->QuestionID, $selectedQuestion->QuestionID);
        $this->assertEquals(4, $selectedQuestion->Level);
    }

    public function test_it_selects_lower_level_after_weak_answer(): void
    {
        $skillSession = $this->createSkillSession([
            'CurrentEstimatedLevel' => 3.0,
        ]);

        $previousQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 3
        );

        AssessmentQuestionAttempt::query()->create([
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
            'QuestionID' => $previousQuestion->QuestionID,
            'QuestionLevel' => 3,
            'AskedAt' => now()->subMinutes(5),
            'AnsweredAt' => now()->subMinutes(4),
            'LlmEvaluationStatus' => 'completed',
            'RawScore' => 30,
            'NormalizedScore' => 0.30,
            'FeedbackText' => 'Weak answer.',
            'EvaluationJson' => ['source' => 'test'],
        ]);

        $lowerLevelQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 2
        );

        $selectedQuestion = $this->service->selectNextQuestion($skillSession);

        $this->assertNotNull($selectedQuestion);
        $this->assertEquals($lowerLevelQuestion->QuestionID, $selectedQuestion->QuestionID);
        $this->assertEquals(2, $selectedQuestion->Level);
    }

    public function test_it_returns_null_when_no_questions_are_available(): void
    {
        $skillSession = $this->createSkillSession([
            'CurrentEstimatedLevel' => 3.0,
        ]);

        $selectedQuestion = $this->service->selectNextQuestion($skillSession);

        $this->assertNull($selectedQuestion);
    }

    public function test_it_prefers_question_from_unused_topic_when_available(): void
    {
        $skillSession = $this->createSkillSession([
            'CurrentEstimatedLevel' => 3.0,
        ]);

        $routingQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 3,
            topic: 'Routing'
        );

        AssessmentQuestionAttempt::query()->create([
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
            'QuestionID' => $routingQuestion->QuestionID,
            'QuestionLevel' => 3,
            'AskedAt' => now()->subMinutes(5),
            'AnsweredAt' => now()->subMinutes(4),
            'LlmEvaluationStatus' => 'completed',
            'RawScore' => 80,
            'NormalizedScore' => 0.80,
            'FeedbackText' => 'Test feedback.',
            'EvaluationJson' => ['source' => 'test'],
        ]);

        $anotherRoutingQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 3,
            topic: 'Routing'
        );

        $eloquentQuestion = $this->createQuestion(
            skillId: $skillSession->SkillID,
            level: 3,
            topic: 'Eloquent'
        );

        $selectedQuestion = $this->service->selectNextQuestion($skillSession);

        $event = \App\Models\AssessmentEvent::query()
            ->where('assessment_skill_session_id', $skillSession->AssessmentSkillSessionID)
            ->where('event_type', 'question_selected')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('Eloquent', $event->payload['selected_topic']);
        $this->assertEquals(['Routing'], $event->payload['used_topics']);
        $this->assertTrue($event->payload['topic_diversity_applied']);

        $this->assertNotNull($selectedQuestion);
        $this->assertEquals($eloquentQuestion->QuestionID, $selectedQuestion->QuestionID);
        $this->assertNotEquals($anotherRoutingQuestion->QuestionID, $selectedQuestion->QuestionID);
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

    private function createQuestion(
        int $skillId,
        int $level,
        ?int $careerPathId = null,
        bool $isActive = true,
        ?string $topic = null
    ): QuestionBank {
        return QuestionBank::query()->create([
            'SkillID' => $skillId,
            'CareerPathID' => $careerPathId,
            'Level' => $level,
            'QuestionType' => 'open_text',
            'Topic' => $topic,
            'QuestionText' => 'Explain this concept.',
            'ExpectedAnswerType' => 'text',
            'DifficultyWeight' => 1.0,
            'IsActive' => $isActive,
        ]);
    }
}
