<?php

namespace Tests\Feature;

use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\CareerPathSkill;
use App\Models\LearningResource;
use App\Models\Skill;
use App\Models\User;
use App\Services\Recommendations\LearningPathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningPathServiceTest extends TestCase
{
    use RefreshDatabase;

    private LearningPathService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LearningPathService::class);
    }

    public function test_it_generates_learning_path_with_assessment_context(): void
    {
        [$session, $skill] = $this->createAssessmentContext();

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

        $path = $this->service->generate($session);

        $this->assertCount(1, $path);

        $item = $path[0];

        $this->assertEquals($skill->id, $item['skill_id']);
        $this->assertEquals('Laravel', $item['skill_name']);
        $this->assertEquals(3.2, $item['current_level']);
        $this->assertEquals(4.0, $item['target_level']);
        $this->assertEquals('medium', $item['priority']);

        $this->assertEquals(0.72, $item['confidence_score']);
        $this->assertEquals(0.60, $item['topic_coverage_ratio']);
        $this->assertEquals(['Routing', 'Eloquent'], $item['tested_topics']);
        $this->assertEquals(['Validation'], $item['improvement_topics']);
        $this->assertEquals('متوسطة', $item['assessment_reliability']);

        $this->assertCount(1, $item['resources']);
        $this->assertEquals('Laravel Eloquent Basics', $item['resources'][0]['title']);
    }

    private function createAssessmentContext(): array
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
            'name' => 'Laravel',
            'category' => 'Backend',
            'normalized_name' => 'laravel_'.uniqid(),
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

        return [$session, $skill];
    }
}
