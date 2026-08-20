<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('market:import-api-jobs', [
    '--pages' => 10,
    '--limit' => 1000,
])
    ->daily()
    ->withoutOverlapping(120)
    ->name('market-analysis-daily-import')
    ->appendOutputTo(
        storage_path('logs/market-analysis-scheduler.log')
    );

Schedule::command('backup:clean --disable-notifications')
    ->dailyAt('01:30')
    ->withoutOverlapping(120);

Schedule::command('backup:run --only-db --disable-notifications')
    ->dailyAt('02:00')
    ->withoutOverlapping(120)
    ->onSuccess(function () {
        Artisan::call('app:backup-to-drive');
    });
