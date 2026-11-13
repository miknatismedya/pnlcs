<?php

namespace Modules\Servers\Voyar;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Servers\Voyar\Console\InstallPendingApps;

class VoyarServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/views', 'voyar');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'voyar');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallPendingApps::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('voyar:install-apps')->everyFiveMinutes()->withoutOverlapping();
        });
    }
}
