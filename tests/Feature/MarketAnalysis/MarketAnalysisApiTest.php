<?php

namespace Tests\Feature\MarketAnalysis;

use App\Models\MarketJobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketAnalysisApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAsMarketApiUser(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Market API Test User',
            'email' => 'market_api_test_'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'is_active' => 1,
            'email_verified' => 1,
            'is_verified_by_admin' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs(
            User::query()->findOrFail($userId)
        );
    }

    public function test_it_lists_market_analysis_career_paths(): void
    {
        $this->actingAsMarketApiUser();

        $careerPathId = DB::table('career_paths')->insertGetId([
            'Name' => 'API Test Backend Path',
            'Description' => 'Temporary API test path.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MarketJobPosting::create([
            'source_type' => 'test',
            'source_name' => 'phpunit',
            'external_id' => 'api-career-path-'.uniqid(),
            'title' => 'Backend Developer',
            'description' => 'PHP Laravel Git required.',
            'language' => 'en',
            'career_path_id' => $careerPathId,
            'published_at' => now(),
            'imported_at' => now(),
            'status' => 'active',
            'content_hash' => hash('sha256', 'api-career-path-'.uniqid()),
        ]);

        $response = $this->getJson('/api/market-analysis/career-paths?only_with_market_data=1');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Market analysis career paths retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'career_paths' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'total_job_postings',
                            'latest_snapshot_date',
                            'has_market_data',
                        ],
                    ],
                ],
            ]);

        $this->assertTrue(
            collect($response->json('data.career_paths'))
                ->contains('id', $careerPathId)
        );
    }

    public function test_it_returns_market_trend_snapshot(): void
    {
        $this->actingAsMarketApiUser();

        $careerPathId = DB::table('career_paths')->insertGetId([
            'Name' => 'API Test Trends Path',
            'Description' => 'Temporary API test path.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $skillId = DB::table('skills')->insertGetId([
            'name' => 'API Trend Skill',
            'category' => 'Framework',
            'normalized_name' => 'api_trend_skill_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_trends')->insert([
            'career_path_id' => $careerPathId,
            'skill_id' => $skillId,
            'demand_score' => 75.00,
            'trend_direction' => 'new',
            'source_job_count' => 3,
            'analyzed_date' => '2026-07-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/market-analysis/career-paths/{$careerPathId}/trends");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Market trend snapshot retrieved successfully.',
                'data' => [
                    'analyzed_date' => '2026-07-21',
                    'total_skills' => 1,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'career_path',
                    'analyzed_date',
                    'total_skills',
                    'trends' => [
                        '*' => [
                            'skill_id',
                            'skill_name',
                            'skill_category',
                            'demand_score',
                            'trend_direction',
                            'source_job_count',
                            'analyzed_date',
                        ],
                    ],
                    'trend_map',
                ],
            ]);
    }

    public function test_it_returns_skill_evidence(): void
    {
        $this->actingAsMarketApiUser();

        $careerPathId = DB::table('career_paths')->insertGetId([
            'Name' => 'API Test Evidence Path',
            'Description' => 'Temporary API test path.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $skillId = DB::table('skills')->insertGetId([
            'name' => 'API Evidence Skill',
            'category' => 'Soft Skill',
            'normalized_name' => 'api_evidence_skill_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $aliasId = DB::table('skill_aliases')->insertGetId([
            'SkillID' => $skillId,
            'Alias' => 'API Evidence Alias '.uniqid(),
            'LanguageCode' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $posting = MarketJobPosting::create([
            'source_type' => 'test',
            'source_name' => 'phpunit',
            'external_id' => 'api-evidence-'.uniqid(),
            'title' => 'Backend Developer',
            'description' => 'Candidate should have API Evidence Skill.',
            'language' => 'en',
            'career_path_id' => $careerPathId,
            'published_at' => now(),
            'imported_at' => now(),
            'status' => 'active',
            'content_hash' => hash('sha256', 'api-evidence-'.uniqid()),
        ]);

        DB::table('market_job_posting_skill_occurrences')->insert([
            'market_job_posting_id' => $posting->id,
            'skill_id' => $skillId,
            'skill_alias_id' => $aliasId,
            'matched_text' => 'API Evidence Skill',
            'language' => 'en',
            'confidence' => 1.00,
            'extraction_method' => 'alias_match',
            'context' => 'Candidate should have API Evidence Skill.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(
            "/api/market-analysis/career-paths/{$careerPathId}/skills/{$skillId}/evidence"
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Skill evidence retrieved successfully.',
                'data' => [
                    'skill_id' => $skillId,
                    'total_returned' => 1,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'career_path',
                    'skill_id',
                    'total_returned',
                    'evidence' => [
                        '*' => [
                            'job_posting',
                            'skill',
                            'evidence',
                        ],
                    ],
                ],
            ]);
    }
}
