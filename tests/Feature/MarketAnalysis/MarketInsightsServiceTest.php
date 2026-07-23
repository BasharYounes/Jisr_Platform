<?php

namespace Tests\Feature\MarketAnalysis;

use App\Models\MarketJobPosting;
use App\Services\MarketAnalysis\MarketInsightsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketInsightsServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_calculates_skill_demand_percentage_by_career_path(): void
    {
        $careerPathId = DB::table('career_paths')->insertGetId([
            'Name' => 'Test Insights Path',
            'Description' => 'Temporary test path.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $phpSkillId = DB::table('skills')->insertGetId([
            'name' => 'PHP Test',
            'category' => 'Programming Language',
            'normalized_name' => 'php_test_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dockerSkillId = DB::table('skills')->insertGetId([
            'name' => 'Docker Test',
            'category' => 'DevOps Tool',
            'normalized_name' => 'docker_test_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $postingOne = MarketJobPosting::create([
            'source_type' => 'test',
            'source_name' => 'phpunit',
            'external_id' => 'insights-1-' . uniqid(),
            'title' => 'Backend Developer 1',
            'description' => 'PHP and Docker required.',
            'language' => 'en',
            'career_path_id' => $careerPathId,
            'published_at' => now(),
            'imported_at' => now(),
            'status' => 'active',
            'content_hash' => hash('sha256', 'insights-1-' . uniqid()),
        ]);

        $postingTwo = MarketJobPosting::create([
            'source_type' => 'test',
            'source_name' => 'phpunit',
            'external_id' => 'insights-2-' . uniqid(),
            'title' => 'Backend Developer 2',
            'description' => 'PHP required.',
            'language' => 'en',
            'career_path_id' => $careerPathId,
            'published_at' => now(),
            'imported_at' => now(),
            'status' => 'active',
            'content_hash' => hash('sha256', 'insights-2-' . uniqid()),
        ]);

        DB::table('market_job_posting_skill_occurrences')->insert([
            [
                'market_job_posting_id' => $postingOne->id,
                'skill_id' => $phpSkillId,
                'matched_text' => 'PHP',
                'language' => 'en',
                'confidence' => 1.00,
                'extraction_method' => 'alias_match',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'market_job_posting_id' => $postingOne->id,
                'skill_id' => $dockerSkillId,
                'matched_text' => 'Docker',
                'language' => 'en',
                'confidence' => 1.00,
                'extraction_method' => 'alias_match',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'market_job_posting_id' => $postingTwo->id,
                'skill_id' => $phpSkillId,
                'matched_text' => 'PHP',
                'language' => 'en',
                'confidence' => 1.00,
                'extraction_method' => 'alias_match',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $result = app(MarketInsightsService::class)
            ->getSkillDemandByCareerPath($careerPathId);

        $php = $result['skills']->firstWhere('skill_id', $phpSkillId);
        $docker = $result['skills']->firstWhere('skill_id', $dockerSkillId);

        $this->assertSame(2, $result['total_job_postings']);

        $this->assertSame(100.0, $php['demand_percentage']);
        $this->assertSame('core', $php['demand_level']);

        $this->assertSame(50.0, $docker['demand_percentage']);
        $this->assertSame('important', $docker['demand_level']);
    }
}
