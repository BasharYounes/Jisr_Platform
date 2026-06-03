<?php

namespace Tests\Feature;

use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\CareerPathSkill;
use App\Models\LearningResource;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningPathApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_path_api_returns_recommendations_with_assessment_context(): void
    {
        [$user, $session, $skill] = $this->createAssessmentContext();

        LearningResource::query()->create([
            'SkillID' => $skill->id,
            'Title' => 'Laravel Eloquent Basics',
            'Url' => 'https://example.com/eloquent',
            'Type' => 'course',
            'Level' => 4,
            'EstimatedHours' => 3.5,
            'Provider' => 'Example Academy',
            'Language' => 'ar',
            'IsFree' => true,
            'IsActive' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/assessments/{$session->AssessmentSessionID}/learning-path");

        $response->assertOk();
        $response->assertJsonPath('message', 'Learning path generated');

        $response->assertJsonPath('data.0.skill_id', $skill->id);
        $response->assertJsonPath('data.0.skill_name', 'Laravel');
        $response->assertJsonPath('data.0.current_level', 3.2);
        $response->assertJsonPath('data.0.target_level', 4);
        $response->assertJsonPath('data.0.priority', 'medium');

        $response->assertJsonPath('data.0.confidence_score', 0.72);
        $response->assertJsonPath('data.0.topic_coverage_ratio', 0.60);
        $response->assertJsonPath('data.0.tested_topics.0', 'Routing');
        $response->assertJsonPath('data.0.tested_topics.1', 'Eloquent');
        $response->assertJsonPath('data.0.improvement_topics.0', 'Validation');
        $response->assertJsonPath('data.0.assessment_reliability', 'متوسطة');

        $response->assertJsonPath('data.0.resources.0.title', 'Laravel Eloquent Basics');
        $response->assertJsonPath('data.0.resources.0.level', 4);
        $response->assertJsonPath('data.0.resources.0.provider', 'Example Academy');
    }

    public function test_learning_path_api_returns_403_for_other_user_session(): void
    {
        [$owner, $session] = $this->createAssessmentContext();

        $otherUser = User::query()->create([
            'name' => 'Other Student',
            'email' => 'other_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->getJson("/api/assessments/{$session->AssessmentSessionID}/learning-path");

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Unauthorized');
    }

    private function createAssessmentContext(): array
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
            'name' => 'Laravel',
            'category' => 'Backend',
            'normalized_name' => 'laravel_' . uniqid(),
        ]);

        CareerPathSkill::query()->create([
            'CareerPathID' => $careerPath->CareerPathID,
            'SkillID' => $skill->id,
            'RequiredLevel' => 4.0,
            'Weight' => 1.00,
            'IsCore' => true,
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
                    'initial_level' => 3.0,
                    'final_level' => 3.2,
                    'confidence_score' => 0.72,
                    'status' => 'completed',
                    'tested_topics' => ['Routing', 'Eloquent'],
                    'improvement_topics' => ['Validation'],
                    'topic_coverage_ratio' => 0.60,
                ],
            ],
        ]);

        AssessmentSkillSession::query()->create([
            'AssessmentSessionID' => $session->AssessmentSessionID,
            'SkillID' => $skill->id,
            'InitialLevel' => 3.0,
            'CurrentEstimatedLevel' => 3.0,
            'FinalLevel' => 3.2,
            'ConfidenceScore' => 0.72,
            'QuestionCount' => 5,
            'Status' => 'completed',
            'CompletedAt' => now(),
        ]);

        return [$user, $session, $skill];
    }
}
