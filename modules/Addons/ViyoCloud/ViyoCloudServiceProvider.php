<?php

namespace Modules\Addons\ViyoCloud;

use App\View\Composers\LanguageComposer;
use App\View\Composers\ThemeComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViyoCloudServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/views', 'viyocloud');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'viyocloud');

        // The branding, colours and language list the core's own public pages get.
        View::composer('viyocloud::*', ThemeComposer::class);
        View::composer('viyocloud::*', LanguageComposer::class);
    }
}
