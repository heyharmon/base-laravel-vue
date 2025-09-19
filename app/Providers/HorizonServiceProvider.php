<?php

namespace App\Providers;

use Laravel\Horizon\HorizonApplicationServiceProvider;
use Laravel\Horizon\Horizon;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return in_array($user->email, [
                'ryan.harmon@metrifi.com'
            ]);
        });
    }
}
