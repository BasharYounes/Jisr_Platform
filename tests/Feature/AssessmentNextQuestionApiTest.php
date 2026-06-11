<?php

namespace Tests\Feature;

// use App\Models\AssessmentQuestionAttempt;
use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\QuestionBank;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentNextQuestionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_question_returns_question_and_creates_attempt(): void
    {
        [$user, $session, $skill, $skillSession] = $this->createAssessmentContext();

        $question = $this->createQuestion(
            skillId: $skill->id,
            level: 3,
            careerPathId: $session->CareerPathID
        );

        $response = $this
            ->actingAs($user)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/skills/{$skill->id}/next-question");

        $response->assertOk();

        $response->assertJsonPath('message', 'Next question retrieved successfully.');
        $response->assertJsonPath('data.question_id', $question->QuestionID);
        $response->assertJsonPath('data.question_level', 3);
        $response->assertJsonPath('data.skill_id', $skill->id);

        $this->assertDatabaseHas('assessment_question_attempts', [
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
            'QuestionID' => $question->QuestionID,
            'QuestionLevel' => 3,
            'LlmEvaluationStatus' => 'pending',
        ]);
    }

    public function test_next_question_does_not_create_attempt_when_skill_is_completed(): void
    {
        [$user, $session, $skill, $skillSession] = $this->createAssessmentContext([
            'Status' => 'completed',
            'FinalLevel' => 3.5,
            'ConfidenceScore' => 0.80,
            'CompletedAt' => now(),
        ]);

        $this->createQuestion(
            skillId: $skill->id,
            level: 3,
            careerPathId: $session->CareerPathID
        );

        $response = $this
            ->actingAs($user)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/skills/{$skill->id}/next-question");

        $response->assertOk();
        $response->assertJsonPath('message', 'This skill assessment is already completed.');

        $this->assertDatabaseMissing('assessment_question_attempts', [
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
        ]);
    }

    public function test_next_question_returns_404_when_no_questions_available(): void
    {
        [$user, $session, $skill] = $this->createAssessmentContext();

        $response = $this
            ->actingAs($user)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/skills/{$skill->id}/next-question");

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'No more questions available for this skill.');
    }

    public function test_next_question_returns_404_when_skill_session_not_found(): void
    {
        [$user, $session] = $this->createAssessmentContext();

        $otherSkill = Skill::query()->create([
            'name' => 'Vue '.uniqid(),
            'category' => 'Frontend',
            'normalized_name' => 'vue_'.uniqid(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson("/api/assessments/{$session->AssessmentSessionID}/skills/{$otherSkill->id}/next-question");

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Skill session not found.');
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

    private function createQuestion(
        int $skillId,
        int $level,
        ?int $careerPathId = null
    ): QuestionBank {
        return QuestionBank::query()->create([
            'SkillID' => $skillId,
            'CareerPathID' => $careerPathId,
            'Level' => $level,
            'QuestionType' => 'open_text',
            'QuestionText' => 'Explain this concept.',
            'ExpectedAnswerType' => 'text',
            'DifficultyWeight' => 1.0,
            'IsActive' => true,
        ]);
    }
}
