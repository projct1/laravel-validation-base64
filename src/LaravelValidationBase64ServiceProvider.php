<?php

declare(strict_types=1);

namespace Projct1\LaravelValidationBase64;

use Illuminate\Support\ServiceProvider;

class LaravelValidationBase64ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'base64');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/base64'),
        ], 'base64-lang');
    }
}
