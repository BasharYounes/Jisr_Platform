<?php

namespace Tests\Feature\MarketAnalysis;

use App\Services\MarketAnalysis\MarketSkillDemandContextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketSkillDemandContextServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_matches_market_context_by_normalized_name_when_skill_id_is_different(): void
    {
        $careerPathId = DB::table('career_paths')->insertGetId([
            'Name' => 'Backend Developer Test',
            'Description' => 'Backend test path',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assessmentSkillId = DB::table('skills')->insertGetId([
            'name' => 'Git',
            'category' => 'Version Control',
            'normalized_name' => 'git',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $marketSkillId = DB::table('skills')->insertGetId([
            'name' => 'Git',
            'category' => 'Version Control',
            'normalized_name' => 'git',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            DB::table('market_job_postings')->insert([
                'source_type' => 'dataset',
                'source_name' => 'normalized_name_fallback_test',
                'external_id' => "fallback-job-{$i}",
                'url' => null,
                'title' => "Backend Job {$i}",
                'description' => 'Backend job requiring Git.',
                'company_name' => 'Test Company',
                'location' => 'Remote',
                'language' => 'en',
                'career_path_id' => $careerPathId,
                'published_at' => now(),
                'imported_at' => now(),
                'status' => 'active',
                'content_hash' => hash('sha256', "fallback-job-{$i}"),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('market_trends')->insert([
            'career_path_id' => $careerPathId,
            'skill_id' => $marketSkillId,
            'demand_score' => 100,
            'trend_direction' => 'new',
            'source_job_count' => 5,
            'analyzed_date' => '2026-07-24',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contexts = app(MarketSkillDemandContextService::class)
            ->getForSkills($careerPathId, [$assessmentSkillId]);

        $this->assertArrayHasKey($assessmentSkillId, $contexts);

        $market = $contexts[$assessmentSkillId];

        $this->assertTrue($market['available']);
        $this->assertSame(100.0, $market['demand_score']);
        $this->assertSame('core', $market['demand_level']);
        $this->assertSame('normalized_name', $market['matched_by']);
        $this->assertSame($marketSkillId, $market['matched_market_skill_id']);
        $this->assertSame(5, $market['source_job_count']);
        $this->assertSame(5, $market['sample_size']);
    }
}
