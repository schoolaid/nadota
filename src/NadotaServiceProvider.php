<?php

namespace SchoolAid\Nadota;

use Illuminate\Support\ServiceProvider;
use SchoolAid\Nadota\Providers\RouteServiceProvider;
use SchoolAid\Nadota\Providers\ServiceBindingServiceProvider;
use SchoolAid\Nadota\Providers\ResourceServiceProvider;

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
