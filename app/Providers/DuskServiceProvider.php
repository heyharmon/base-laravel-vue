<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Dusk\DuskServiceProvider as BaseDuskServiceProvider;

class DuskServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local', 'testing')) {
            $this->app->register(BaseDuskServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->gate();
    }

    /**
     * Register the Dusk gate.
     *
     * This gate determines who can access Dusk routes in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewDuskDashboard', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }
}
