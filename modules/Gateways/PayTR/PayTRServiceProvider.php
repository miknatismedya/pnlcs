<?php

namespace Modules\Gateways\PayTR;

use Illuminate\Support\ServiceProvider;

class PayTRServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/views', 'paytr');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'paytr');
    }
}
