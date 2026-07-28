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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningPathApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_path_api_returns_recommendations_with_assessment_and_market_context(): void
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

        $response->assertJsonPath('data.0.market.available', true);
        $response->assertJsonPath('data.0.market.demand_score', 80);
        $response->assertJsonPath('data.0.market.demand_level', 'core');
        $response->assertJsonPath('data.0.market.trend_direction', 'new');
        $response->assertJsonPath('data.0.market.source_job_count', 4);
        $response->assertJsonPath('data.0.market.sample_size', 5);
        $response->assertJsonPath('data.0.market.analyzed_date', '2026-07-24');

        $response->assertJsonPath('data.0.market.labels.demand_level', 'مهارة أساسية');
        $response->assertJsonPath('data.0.market.labels.trend_direction', 'بيانات جديدة');
        $response->assertJsonPath('data.0.market.labels.learning_priority', 'أولوية عالية');

        $this->assertStringContainsString(
            'Laravel',
            $response->json('data.0.market.student_message')
        );

        $this->assertStringContainsString(
            '4 من أصل 5',
            $response->json('data.0.market.student_message')
        );

        $response->assertJsonPath('data.0.resources.0.title', 'Laravel Eloquent Basics');
        $response->assertJsonPath('data.0.resources.0.level', 4);
        $response->assertJsonPath('data.0.resources.0.provider', 'Example Academy');
    }

    public function test_learning_path_api_returns_403_for_other_user_session(): void
    {
        [$owner, $session] = $this->createAssessmentContext();

        $otherUser = User::query()->create([
            'name' => 'Other Student',
            'email' => 'other_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->getJson("/api/assessments/{$session->AssessmentSessionID}/learning-path");

        $response->assertForbidden();
    }

    private function createActiveUser(string $name): User
    {
        $userId = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '_', $name)).'_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => 1,
            'email_verified' => 1,
            'is_verified_by_admin' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($userId);
    }

    private function createAssessmentContext(): array
    {
        $user = $this->createActiveUser('Test Student');

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

        $this->createMarketContext(
            careerPathId: (int) $careerPath->CareerPathID,
            skillId: (int) $skill->id
        );

        return [$user, $session, $skill];
    }

    private function createMarketContext(int $careerPathId, int $skillId): void
    {
        for ($i = 1; $i <= 5; $i++) {
            DB::table('market_job_postings')->insert([
                'source_type' => 'dataset',
                'source_name' => 'learning_path_test_dataset',
                'external_id' => "learning-path-job-{$i}",
                'url' => null,
                'title' => "Laravel Backend Job {$i}",
                'description' => 'Laravel backend job posting used for learning path market context tests.',
                'company_name' => 'Learning Path Test Company',
                'location' => 'Remote',
                'language' => 'en',
                'career_path_id' => $careerPathId,
                'published_at' => '2026-07-20 00:00:00',
                'imported_at' => now(),
                'status' => 'active',
                'content_hash' => hash('sha256', "learning-path-market-job-{$careerPathId}-{$skillId}-{$i}"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('market_trends')->insert([
            'career_path_id' => $careerPathId,
            'skill_id' => $skillId,
            'demand_score' => 80,
            'trend_direction' => 'new',
            'source_job_count' => 4,
            'analyzed_date' => '2026-07-24',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
