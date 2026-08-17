<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isDiagnosticEnvironment = $this->app->environment([
    'local',
    'performance',
]);

        Telescope::filter(function (IncomingEntry $entry) use ($isDiagnosticEnvironment) {
    return $isDiagnosticEnvironment ||
           $entry->isReportableException() ||
           $entry->isFailedRequest() ||
           $entry->isFailedJob() ||
           $entry->isScheduledTask() ||
           $entry->hasMonitoredTag();
});
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment(['local', 'performance'])) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
{
    Gate::define('viewTelescope', function (?User $user = null) {
        return $this->app->environment(['local', 'performance']);
    });
}
}
