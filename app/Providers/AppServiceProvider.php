<?php

namespace App\Providers;

use App\Planeward\Webhooks\EloquentWebhookStore;
use App\Planeward\Webhooks\WebhookStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WebhookStore::class, EloquentWebhookStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
