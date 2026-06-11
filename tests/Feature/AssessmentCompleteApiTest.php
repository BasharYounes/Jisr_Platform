<?php

namespace Tests\Feature;

use App\Models\AssessmentEvent;
use App\Models\AssessmentQuestionAttempt;
use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\QuestionBank;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentCompleteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_assessment_updates_user_skill_and_marks_session_completed(): void
    {
        [$user, $session, $skill, $skillSession] = $this->createAssessmentContext();

        $scores = [0.82, 0.84, 0.81, 0.83, 0.85];

        foreach ($scores as $index => $score) {
            $this->createEvaluatedAttempt(
                skillSession: $skillSession,
                score: $score,
                questionLevel: $index < 2 ? 3 : 4
            );
        }

        $response = $this
            ->actingAs($user)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/complete");

        $response->assertOk();
        $response->assertJsonPath('message', 'Assessment session completed successfully.');

        $this->assertDatabaseHas('assessment_sessions', [
            'AssessmentSessionID' => $session->AssessmentSessionID,
            'Status' => 'completed',
        ]);

        $this->assertDatabaseHas('assessment_skill_sessions', [
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
            'Status' => 'completed',
        ]);

        $this->assertDatabaseHas('user_skills', [
            'UserId' => $user->id,
            'SkillId' => $skill->id,
            'Source' => 'ai_assessment',
            'Verified' => true,
        ]);

        $userSkill = UserSkill::query()
            ->where('UserId', $user->id)
            ->where('SkillId', $skill->id)
            ->first();

        $this->assertNotNull($userSkill);
        $this->assertNotNull($userSkill->ProficiencyLevel);
        $this->assertNotNull($userSkill->ConfidenceScore);
    }

    public function test_complete_assessment_returns_403_for_other_user_session(): void
    {
        [$owner, $session] = $this->createAssessmentContext();

        $otherUser = User::query()->create([
            'name' => 'Other Student',
            'email' => 'other_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/complete");

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Unauthorized access to this assessment session.');
    }

    public function test_complete_assessment_saves_final_results_json(): void
    {
        [$user, $session, $skill, $skillSession] = $this->createAssessmentContext();

        $scores = [0.82, 0.84, 0.81, 0.83, 0.85];

        foreach ($scores as $index => $score) {
            $this->createEvaluatedAttempt(
                skillSession: $skillSession,
                score: $score,
                questionLevel: $index < 2 ? 3 : 4
            );
        }

        $response = $this
            ->actingAs($user)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/complete");

        $response->assertOk();

        $session->refresh();

        $this->assertEquals('completed', $session->Status);
        $this->assertNotNull($session->CompletedAt);
        $this->assertNotNull($session->FinalResultsJson);
        $this->assertIsArray($session->FinalResultsJson);

        $this->assertEquals($skill->id, $session->FinalResultsJson[0]['skill_id']);
        $this->assertNotNull($session->FinalResultsJson[0]['final_level']);
        $this->assertNotNull($session->FinalResultsJson[0]['confidence_score']);
    }

    public function test_complete_assessment_records_completion_reason_in_telemetry(): void
    {
        [$user, $session, $skill, $skillSession] = $this->createAssessmentContext();

        $scores = [0.82, 0.84, 0.81, 0.83, 0.85];

        foreach ($scores as $index => $score) {
            $this->createEvaluatedAttempt(
                skillSession: $skillSession,
                score: $score,
                questionLevel: $index < 2 ? 3 : 4
            );
        }

        $response = $this
            ->actingAs($user)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/complete");

        $response->assertOk();

        $event = AssessmentEvent::query()
            ->where('assessment_session_id', $session->AssessmentSessionID)
            ->where('assessment_skill_session_id', $skillSession->AssessmentSkillSessionID)
            ->where('event_type', 'skill_session_completed')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('confidence_threshold_reached', $event->payload['completion_reason']);
        $this->assertEquals(5, $event->payload['question_count']);
        $this->assertNotNull($event->level_after);
        $this->assertNotNull($event->confidence_score);
    }

    public function test_complete_assessment_records_topic_coverage_in_telemetry(): void
    {
        [$user, $session, $skill, $skillSession] = $this->createAssessmentContext();

        $topics = ['Routing', 'Eloquent', 'Validation', 'Middleware', 'Testing'];

        foreach ($topics as $topic) {
            $this->createQuestion(
                skillId: $skill->id,
                level: 3,
                careerPathId: $session->CareerPathID,
                topic: $topic
            );
        }

        $attemptData = [
            ['score' => 0.82, 'level' => 3, 'topic' => 'Routing'],
            ['score' => 0.84, 'level' => 3, 'topic' => 'Eloquent'],
            ['score' => 0.81, 'level' => 4, 'topic' => 'Validation'],
            ['score' => 0.83, 'level' => 4, 'topic' => 'Routing'],
            ['score' => 0.85, 'level' => 5, 'topic' => 'Eloquent'],
        ];

        foreach ($attemptData as $item) {
            $this->createEvaluatedAttempt(
                skillSession: $skillSession,
                score: $item['score'],
                questionLevel: $item['level'],
                topic: $item['topic']
            );
        }

        $response = $this
            ->actingAs($user)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/complete");

        $response->assertOk();

        $event = AssessmentEvent::query()
            ->where('assessment_session_id', $session->AssessmentSessionID)
            ->where('assessment_skill_session_id', $skillSession->AssessmentSkillSessionID)
            ->where('event_type', 'skill_session_completed')
            ->first();

        $this->assertNotNull($event);

        $this->assertEqualsCanonicalizing(
            ['Routing', 'Eloquent', 'Validation'],
            $event->payload['tested_topics']
        );

        $this->assertEquals(3, $event->payload['topic_count']);
        $this->assertEquals(5, $event->payload['available_topic_count']);
        $this->assertEquals(0.60, $event->payload['topic_coverage_ratio']);
    }

    public function test_complete_assessment_saves_topic_coverage_in_final_results_json(): void
    {
        [$user, $session, $skill, $skillSession] = $this->createAssessmentContext();

        $topics = ['Routing', 'Eloquent', 'Validation', 'Middleware', 'Testing'];

        foreach ($topics as $topic) {
            $this->createQuestion(
                skillId: $skill->id,
                level: 3,
                careerPathId: $session->CareerPathID,
                topic: $topic
            );
        }

        $attemptData = [
            ['score' => 0.82, 'level' => 3, 'topic' => 'Routing'],
            ['score' => 0.84, 'level' => 3, 'topic' => 'Eloquent'],
            ['score' => 0.81, 'level' => 4, 'topic' => 'Validation'],
            ['score' => 0.83, 'level' => 4, 'topic' => 'Routing'],
            ['score' => 0.85, 'level' => 5, 'topic' => 'Eloquent'],
        ];

        foreach ($attemptData as $item) {
            $this->createEvaluatedAttempt(
                skillSession: $skillSession,
                score: $item['score'],
                questionLevel: $item['level'],
                topic: $item['topic']
            );
        }

        $response = $this
            ->actingAs($user)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/complete");

        $response->assertOk();

        $session->refresh();

        $result = $session->FinalResultsJson[0];

        $this->assertEqualsCanonicalizing(
            ['Routing', 'Eloquent', 'Validation'],
            $result['tested_topics']
        );

        $this->assertEquals(3, $result['topic_count']);
        $this->assertEquals(5, $result['available_topic_count']);
        $this->assertEquals(0.60, $result['topic_coverage_ratio']);
    }

    public function test_complete_assessment_updates_user_skill_with_topic_adjusted_confidence(): void
    {
        [$user, $session, $skill, $skillSession] = $this->createAssessmentContext();

        $availableTopics = ['Routing', 'Eloquent', 'Validation', 'Middleware', 'Testing'];

        foreach ($availableTopics as $topic) {
            $this->createQuestion(
                skillId: $skill->id,
                level: 3,
                careerPathId: $session->CareerPathID,
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

        $response = $this
            ->actingAs($user)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/complete");

        $response->assertOk();

        $skillSession->refresh();

        $userSkill = UserSkill::query()
            ->where('UserId', $user->id)
            ->where('SkillId', $skill->id)
            ->first();

        $this->assertNotNull($userSkill);
        $this->assertEquals(
            (float) $skillSession->ConfidenceScore,
            (float) $userSkill->ConfidenceScore
        );
    }

    private function createAssessmentContext(array $skillSessionOverrides = []): array
    {
        $user = User::query()->create([
            'name' => 'Test Student',
            'email' => 'student_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $careerPath = CareerPath::query()->create([
            'Name' => 'Backend Developer '.uniqid(),
            'Description' => 'Backend development path',
        ]);

        $skill = Skill::query()->create([
            'name' => 'Laravel '.uniqid(),
            'category' => 'Backend',
            'normalized_name' => 'laravel_'.uniqid(),
        ]);

        $session = AssessmentSession::query()->create([
            'UserID' => $user->id,
            'CareerPathID' => $careerPath->CareerPathID,
            'Status' => 'in_progress',
            'StartedAt' => now(),
        ]);

        $skillSession = AssessmentSkillSession::query()->create(array_merge([
            'AssessmentSessionID' => $session->AssessmentSessionID,
            'SkillID' => $skill->id,
            'InitialLevel' => 3.0,
            'CurrentEstimatedLevel' => 3.0,
            'FinalLevel' => null,
            'ConfidenceScore' => null,
            'QuestionCount' => 0,
            'Status' => 'in_progress',
            'CompletedAt' => null,
        ], $skillSessionOverrides));

        return [$user, $session, $skill, $skillSession];
    }

    private function createEvaluatedAttempt(
        AssessmentSkillSession $skillSession,
        float $score,
        int $questionLevel,
        float $difficultyWeight = 1.0,
        ?string $topic = null
    ): AssessmentQuestionAttempt {
        $question = QuestionBank::query()->create([
            'SkillID' => $skillSession->SkillID,
            'CareerPathID' => $skillSession->assessmentSession->CareerPathID,
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
            'EvaluationJson' => ['source' => 'test'],
        ]);
    }

    private function createQuestion(
        int $skillId,
        int $level,
        ?int $careerPathId = null,
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
            'IsActive' => true,
        ]);
    }
}
