<?php

namespace Tests\Feature;

use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\CareerPathSkill;
use App\Models\Skill;
use App\Models\User;
use App\Services\Recommendations\SkillGapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillGapServiceTest extends TestCase
{
    use RefreshDatabase;

    private SkillGapService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SkillGapService::class);
    }

    public function test_it_calculates_skill_gap_with_assessment_context(): void
    {
        [$session, $skill] = $this->createAssessmentContext(
            requiredLevel: 4.0,
            finalLevel: 3.2,
            confidenceScore: 0.72,
            topicCoverageRatio: 0.60
        );

        $gaps = $this->service->calculateForSession($session);

        $this->assertCount(1, $gaps);

        $gap = $gaps[0];

        $this->assertEquals($skill->id, $gap['skill_id']);
        $this->assertEquals('Laravel', $gap['skill_name']);
        $this->assertEquals(4.0, $gap['required_level']);
        $this->assertEquals(3.2, $gap['actual_level']);
        $this->assertEquals(0.8, $gap['gap']);
        $this->assertEquals('medium', $gap['priority']);
        $this->assertEquals('needs_improvement', $gap['status']);

        $this->assertEquals(0.72, $gap['confidence_score']);
        $this->assertEquals(0.60, $gap['topic_coverage_ratio']);
        $this->assertEquals(['Routing', 'Eloquent'], $gap['tested_topics']);
        $this->assertEquals(['Validation'], $gap['improvement_topics']);
        $this->assertEquals('متوسطة', $gap['assessment_reliability']);
    }

    public function test_it_marks_skill_as_sufficient_when_actual_level_meets_required_level(): void
    {
        [$session] = $this->createAssessmentContext(
            requiredLevel: 3.0,
            finalLevel: 3.5,
            confidenceScore: 0.80,
            topicCoverageRatio: 0.75
        );

        $gaps = $this->service->calculateForSession($session);

        $gap = $gaps[0];

        $this->assertEquals(0.0, $gap['gap']);
        $this->assertEquals('none', $gap['priority']);
        $this->assertEquals('sufficient', $gap['status']);
        $this->assertEquals('عالية', $gap['assessment_reliability']);
    }

    private function createAssessmentContext(
        float $requiredLevel,
        float $finalLevel,
        float $confidenceScore,
        float $topicCoverageRatio
    ): array {
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
            'RequiredLevel' => $requiredLevel,
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
                    'final_level' => $finalLevel,
                    'confidence_score' => $confidenceScore,
                    'status' => 'completed',
                    'tested_topics' => ['Routing', 'Eloquent'],
                    'improvement_topics' => ['Validation'],
                    'topic_coverage_ratio' => $topicCoverageRatio,
                ],
            ],
        ]);

        AssessmentSkillSession::query()->create([
            'AssessmentSessionID' => $session->AssessmentSessionID,
            'SkillID' => $skill->id,
            'InitialLevel' => 3.0,
            'CurrentEstimatedLevel' => 3.0,
            'FinalLevel' => $finalLevel,
            'ConfidenceScore' => $confidenceScore,
            'QuestionCount' => 5,
            'Status' => 'completed',
            'CompletedAt' => now(),
        ]);

        return [$session, $skill];
    }
}
