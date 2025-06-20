<?php

namespace Bitress\LaravelSemaphore\Providers;

use Illuminate\Support\ServiceProvider;
use Bitress\LaravelSemaphore\SemaphoreClient;

class SemaphoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/semaphore.php', 'semaphore');

        $this->app->singleton('semaphore-sms', fn($app) => new SemaphoreClient(config('semaphore.api_key')));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/semaphore.php' => config_path('semaphore.php'),
        ], 'config');
    }
}
