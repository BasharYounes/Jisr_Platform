<?php

namespace Tests\Feature\MarketAnalysis;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketAnalysisSeedDemoDataCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_market_seed_demo_data_command_prepares_clean_repeatable_demo_data(): void
    {
        $snapshotDate = '2026-07-24';

        $this->artisan('market:seed-demo-data', [
            '--fresh' => true,
            '--date' => $snapshotDate,
        ])->assertExitCode(Command::SUCCESS);

        $this->artisan('market:seed-demo-data', [
            '--fresh' => true,
            '--date' => $snapshotDate,
        ])->assertExitCode(Command::SUCCESS);

        $careerPathIds = DB::table('career_paths')
            ->whereIn('Name', [
                'Backend Developer',
                'Frontend Developer',
                'Mobile Developer',
            ])
            ->pluck('CareerPathID', 'Name');

        $this->assertCount(3, $careerPathIds);

        $this->assertDemoCareerPath(
            careerPathIds: $careerPathIds,
            careerPathName: 'Backend Developer',
            snapshotDate: $snapshotDate,
            expectedJobPostings: 5,
            expectedTrendSkills: 8
        );

        $this->assertDemoCareerPath(
            careerPathIds: $careerPathIds,
            careerPathName: 'Frontend Developer',
            snapshotDate: $snapshotDate,
            expectedJobPostings: 10,
            expectedTrendSkills: 12
        );

        $this->assertDemoCareerPath(
            careerPathIds: $careerPathIds,
            careerPathName: 'Mobile Developer',
            snapshotDate: $snapshotDate,
            expectedJobPostings: 10,
            expectedTrendSkills: 10
        );

        $this->assertSame(
            25,
            DB::table('market_job_postings')
                ->whereIn('career_path_id', $careerPathIds->values())
                ->where('status', 'active')
                ->count()
        );

        $this->assertSame(
            30,
            DB::table('market_trends')
                ->whereIn('career_path_id', $careerPathIds->values())
                ->where('analyzed_date', $snapshotDate)
                ->count()
        );
    }

    private function assertDemoCareerPath(
        Collection $careerPathIds,
        string $careerPathName,
        string $snapshotDate,
        int $expectedJobPostings,
        int $expectedTrendSkills
    ): void {
        $careerPathId = (int) $careerPathIds->get($careerPathName);

        $this->assertGreaterThan(0, $careerPathId);

        $this->assertSame(
            $expectedJobPostings,
            DB::table('market_job_postings')
                ->where('career_path_id', $careerPathId)
                ->where('status', 'active')
                ->count()
        );

        $this->assertSame(
            $expectedTrendSkills,
            DB::table('market_trends')
                ->where('career_path_id', $careerPathId)
                ->where('analyzed_date', $snapshotDate)
                ->count()
        );
    }
}
