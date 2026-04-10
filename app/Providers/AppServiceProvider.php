<?php

namespace App\Providers;

use App\Models\StaticGroup;
use App\Policies\StaticGroupPermissionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
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

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('battlenet', BattlenetProvider::class);
            $event->extendSocialite('discord', DiscordProvider::class);
        });
    }
}
