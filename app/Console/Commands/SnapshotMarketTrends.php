<?php

namespace App\Console\Commands;

use App\Models\CareerPath;
use App\Services\MarketAnalysis\MarketTrendSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SnapshotMarketTrends extends Command
{
    protected $signature = 'market:snapshot-trends
        {--career-path-id= : Snapshot trends for a specific career path}
        {--date= : Snapshot date, example: 2026-07-21}';

    protected $description = 'Store calculated market skill demand snapshots into market_trends.';

    public function handle(MarketTrendSnapshotService $snapshotService): int
    {
        $careerPathId = $this->option('career-path-id');
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now()->startOfDay();

        $careerPathsQuery = CareerPath::query();

        if ($careerPathId !== null) {
            $careerPathsQuery->where('CareerPathID', (int) $careerPathId);
        }

        $careerPaths = $careerPathsQuery->get();

        if ($careerPaths->isEmpty()) {
            $this->warn('No career paths found.');
            return self::SUCCESS;
        }

        foreach ($careerPaths as $careerPath) {
            $result = $snapshotService->snapshotCareerPath(
                careerPathId: $careerPath->CareerPathID,
                analyzedDate: $date
            );

            $this->info(
                "Career Path #{$result['career_path_id']} | " .
                "Date: {$result['analyzed_date']} | " .
                "Job Postings: {$result['total_job_postings']} | " .
                "Saved Trends: {$result['saved_trends']}"
            );
        }

        return self::SUCCESS;
    }
}
