<?php

namespace Modules\Addons\ViyoCloud;

use App\View\Composers\LanguageComposer;
use App\View\Composers\ThemeComposer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Addons\ViyoCloud\Console\ChargeHourly;

class ViyoCloudServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/views', 'viyocloud');
        // The homepage sections this addon adds (sections.viyo-*), found the way
        // the core's own sections are; the active theme's views still come first.
        View::addLocation(__DIR__.'/views/site');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'viyocloud');
        $this->loadMigrationsFrom(__DIR__.'/migrations');

        // The branding, colours and language list the core's own public pages get.
        View::composer('viyocloud::*', ThemeComposer::class);
        View::composer('viyocloud::*', LanguageComposer::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ChargeHourly::class]);
        }

        // Each hour is charged once whenever the run comes; every five minutes
        // so a top-up starts a stopped server again soon.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('viyo:hourly-charge')->everyFiveMinutes()->withoutOverlapping();
        });
    }
}
