<?php

namespace Tests\Feature\MarketAnalysis;

use App\Models\MarketJobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketAnalysisDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_market_analysis_dashboard(): void
    {
        $adminId = DB::table('users')->insertGetId([
            'name' => 'Market Dashboard Admin',
            'email' => 'market_dashboard_admin@example.com',
            'password' => Hash::make('password'),
            'is_active' => 1,
            'email_verified' => 1,
            'is_verified_by_admin' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::query()->findOrFail($adminId);

        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $careerPathId = DB::table('career_paths')->insertGetId([
            'Name' => 'Dashboard Backend Path',
            'Description' => 'Temporary dashboard test path.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $skillId = DB::table('skills')->insertGetId([
            'name' => 'Dashboard Test Skill',
            'category' => 'Framework',
            'normalized_name' => 'dashboard_test_skill',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $aliasId = DB::table('skill_aliases')->insertGetId([
            'SkillID' => $skillId,
            'Alias' => 'Dashboard Test Alias',
            'LanguageCode' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classifiedPosting = MarketJobPosting::create([
            'source_type' => 'test',
            'source_name' => 'phpunit',
            'external_id' => 'dashboard-classified-job',
            'title' => 'Backend Developer',
            'description' => 'Dashboard Test Skill required.',
            'language' => 'en',
            'career_path_id' => $careerPathId,
            'published_at' => now(),
            'imported_at' => now(),
            'status' => 'active',
            'content_hash' => hash(
                'sha256',
                'dashboard-classified-job'
            ),
        ]);

        $unclassifiedPosting = MarketJobPosting::create([
            'source_type' => 'test',
            'source_name' => 'phpunit',
            'external_id' => 'dashboard-unclassified-job',
            'title' => 'General Software Engineer',
            'description' => 'General job without extracted skills.',
            'language' => 'en',
            'career_path_id' => null,
            'published_at' => now(),
            'imported_at' => now(),
            'status' => 'active',
            'content_hash' => hash(
                'sha256',
                'dashboard-unclassified-job'
            ),
        ]);

        DB::table('market_job_posting_skill_occurrences')->insert([
            'market_job_posting_id' => $classifiedPosting->id,
            'skill_id' => $skillId,
            'skill_alias_id' => $aliasId,
            'matched_text' => 'Dashboard Test Skill',
            'language' => 'en',
            'confidence' => 1.00,
            'extraction_method' => 'alias_match',
            'context' => 'Dashboard Test Skill required.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('market_job_postings')
            ->where('id', $classifiedPosting->id)
            ->update([
                'classification_status' => 'classified',
                'classification_method' => 'weighted_rules_v1',
                'classification_score' => 4.0,
                'classified_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('market_job_postings')
            ->where('id', $unclassifiedPosting->id)
            ->update([
                'classification_status' =>
                    'insufficient_evidence',

                'classification_method' =>
                    'weighted_rules_v1',

                'classification_score' => 0.0,
                'classified_at' => now(),
                'updated_at' => now(),
            ]);

        $response = $this->getJson(
            '/api/admin/market-analysis/dashboard'
        );

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' =>
                    'Market analysis dashboard retrieved successfully',

                'data' => [
                    'job_postings' => [
                        'total' => 2,
                        'classified' => 1,
                        'unclassified' => 1,
                        'classified_percentage' => 50,
                    ],
                    'classification' => [
                        'current_method' => 'weighted_rules_v1',

                        'analyzed_job_postings' => 2,

                        'analysis_coverage_percentage' => 100,

                        'statuses' => [
                            'pending' => [
                                'count' => 0,
                                'percentage' => 0,
                            ],

                            'classified' => [
                                'count' => 1,
                                'percentage' => 50,
                            ],

                            'ambiguous' => [
                                'count' => 0,
                                'percentage' => 0,
                            ],

                            'out_of_scope' => [
                                'count' => 0,
                                'percentage' => 0,
                            ],

                            'insufficient_evidence' => [
                                'count' => 1,
                                'percentage' => 50,
                            ],
                        ],
                    ],

                    'skill_extraction' => [
                        'job_postings_with_skills' => 1,
                        'job_postings_without_skills' => 1,
                        'coverage_percentage' => 50,
                        'total_occurrences' => 1,
                        'unique_skills' => 1,
                    ],
                ],
            ])
            ->assertJsonPath(
                'data.sources.0.source_name',
                'phpunit'
            )
            ->assertJsonPath(
                'data.sources.0.job_postings_count',
                2
            )
            ->assertJsonStructure([
                'data' => [
                    'latest_activity' => [
                        'latest_job_update_at',
                        'latest_published_at',
                    ],
                ],
            ]);
    }

    public function test_guest_cannot_view_market_analysis_dashboard(): void
    {
        $this->getJson('/api/admin/market-analysis/dashboard')
            ->assertUnauthorized();
    }
}
