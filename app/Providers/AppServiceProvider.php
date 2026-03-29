<?php

namespace App\Providers;

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

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('battlenet', BattlenetProvider::class);
            $event->extendSocialite('discord', DiscordProvider::class);
        });
    }
}
