<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MailtrapService;

class MailtrapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailtrapService::class, function ($app) {
            return new MailtrapService();
        });
    }
}