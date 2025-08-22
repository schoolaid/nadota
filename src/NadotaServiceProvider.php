<?php

namespace Said\Nadota;

use Illuminate\Support\ServiceProvider;
use Said\Nadota\Providers\RouteServiceProvider;
use Said\Nadota\Providers\ServiceBindingServiceProvider;
use Said\Nadota\Providers\ResourceServiceProvider;

class NadotaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(ServiceBindingServiceProvider::class);
    }

    public function boot(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(ResourceServiceProvider::class);
    }
}
