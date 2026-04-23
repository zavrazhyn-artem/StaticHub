<?php

namespace App\Providers;

use App\Models\StaticGroup;
use App\Policies\StaticGroupPermissionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Battlenet\Provider as BattlenetProvider;
use SocialiteProviders\Discord\Provider as DiscordProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceScheme('https');

        Gate::policy(StaticGroup::class, StaticGroupPermissionPolicy::class);

        Gate::define('viewLogViewer', function ($user = null) {
            return session('admin_authenticated') === true;
        });

        Gate::define('manage-feedback', function ($user = null) {
            return session('admin_authenticated') === true;
        });

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('battlenet', BattlenetProvider::class);
            $event->extendSocialite('discord', DiscordProvider::class);
        });

        $this->configureJobRateLimiters();
    }

    /**
     * Named rate limiters consumed by jobs via the RateLimited middleware.
     * When the limit is hit, the middleware releases the job back to the
     * queue instead of failing — so syncs slow down gracefully under load
     * instead of saturating CPU / external APIs.
     */
    private function configureJobRateLimiters(): void
    {
        RateLimiter::for('bnet-api', fn () => Limit::perMinute(30));
        RateLimiter::for('rio-api', fn () => Limit::perMinute(30));
        RateLimiter::for('wcl-api', fn () => Limit::perMinute(15));
    }
}
