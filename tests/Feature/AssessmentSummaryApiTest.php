<?php

namespace Tests\Feature;

use App\Models\AssessmentQuestionAttempt;
use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\QuestionBank;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_topic_coverage_from_final_results_json(): void
    {
        [$user, $session, $skill, $skillSession] = $this->createCompletedAssessmentContext();

        $response = $this
            ->actingAs($user)
            ->getJson("/api/assessments/{$session->AssessmentSessionID}/summary");

        $response->assertOk();
        $response->assertJsonPath('message', 'Assessment summary retrieved successfully.');

        $response->assertJsonPath('data.skills.0.skill_id', $skill->id);
        $response->assertJsonPath('data.skills.0.tested_topics.0', 'Routing');
        $response->assertJsonPath('data.skills.0.tested_topics.1', 'Eloquent');
        $response->assertJsonPath('data.skills.0.tested_topics.2', 'Validation');
        $response->assertJsonPath('data.skills.0.topic_count', 3);
        $response->assertJsonPath('data.skills.0.available_topic_count', 5);
        $response->assertJsonPath('data.skills.0.topic_coverage_ratio', 0.60);
    }

    public function test_summary_returns_question_topic_for_attempts(): void
    {
        [$user, $session] = $this->createCompletedAssessmentContext();

        $response = $this
            ->actingAs($user)
            ->getJson("/api/assessments/{$session->AssessmentSessionID}/summary");

        $response->assertOk();
        $response->assertJsonPath('data.skills.0.attempts.0.question_topic', 'Routing');
    }

    public function test_summary_returns_403_for_other_user_session(): void
    {
        [$owner, $session] = $this->createCompletedAssessmentContext();

        $otherUser = User::query()->create([
            'name' => 'Other Student',
            'email' => 'other_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->getJson("/api/assessments/{$session->AssessmentSessionID}/summary");

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Unauthorized access to this assessment session.');
    }

    public function test_summary_returns_arabic_assessment_insights(): void
    {
        [$user, $session] = $this->createCompletedAssessmentContext();

        $response = $this
            ->actingAs($user)
            ->getJson("/api/assessments/{$session->AssessmentSessionID}/summary");

        $response->assertOk();

        $response->assertJsonPath('data.skills.0.insights.level_label', 'متقدم');
        $response->assertJsonPath('data.skills.0.insights.confidence_label', 'جيدة');
        $response->assertJsonPath('data.skills.0.insights.coverage_label', 'تغطية جزئية');

        $this->assertNotEmpty(
            $response->json('data.skills.0.insights.summary_message')
        );
    }

    private function createCompletedAssessmentContext(): array
    {
        $user = User::query()->create([
            'name' => 'Test Student',
            'email' => 'student_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $careerPath = CareerPath::query()->create([
            'Name' => 'Backend Developer ' . uniqid(),
            'Description' => 'Backend development path',
        ]);

        $skill = Skill::query()->create([
            'name' => 'Laravel ' . uniqid(),
            'category' => 'Backend',
            'normalized_name' => 'laravel_' . uniqid(),
        ]);

        $session = AssessmentSession::query()->create([
            'UserID' => $user->id,
            'CareerPathID' => $careerPath->CareerPathID,
            'Status' => 'completed',
            'StartedAt' => now()->subHour(),
            'CompletedAt' => now(),
            'FinalResultsJson' => [
                [
                    'skill_id' => $skill->id,
                    'initial_level' => 3,
                    'final_level' => 4,
                    'confidence_score' => 0.76,
                    'status' => 'completed',
                    'tested_topics' => ['Routing', 'Eloquent', 'Validation'],
                    'topic_count' => 3,
                    'available_topic_count' => 5,
                    'topic_coverage_ratio' => 0.60,
                ],
            ],
        ]);

        $skillSession = AssessmentSkillSession::query()->create([
            'AssessmentSessionID' => $session->AssessmentSessionID,
            'SkillID' => $skill->id,
            'InitialLevel' => 3.0,
            'CurrentEstimatedLevel' => 3.8,
            'FinalLevel' => 4.0,
            'ConfidenceScore' => 0.76,
            'QuestionCount' => 5,
            'Status' => 'completed',
            'CompletedAt' => now(),
        ]);

        $this->createAttempt($skillSession, topic: 'Routing');

        return [$user, $session, $skill, $skillSession];
    }

    private function createAttempt(
        AssessmentSkillSession $skillSession,
        string $topic
    ): AssessmentQuestionAttempt {
        $question = QuestionBank::query()->create([
            'SkillID' => $skillSession->SkillID,
            'CareerPathID' => $skillSession->assessmentSession->CareerPathID,
            'Level' => 3,
            'QuestionType' => 'open_text',
            'Topic' => $topic,
            'QuestionText' => 'Explain this concept.',
            'ExpectedAnswerType' => 'text',
            'DifficultyWeight' => 1.0,
            'IsActive' => true,
        ]);

        return AssessmentQuestionAttempt::query()->create([
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
            'QuestionID' => $question->QuestionID,
            'QuestionLevel' => 3,
            'AskedAt' => now(),
            'AnsweredAt' => now(),
            'LlmEvaluationStatus' => 'completed',
            'RawScore' => 85,
            'NormalizedScore' => 0.85,
            'FeedbackText' => 'Good answer.',
            'EvaluationJson' => ['source' => 'test'],
        ]);
    }
}
