<?php

namespace SchoolAid\Nadota;

use Illuminate\Support\ServiceProvider;
use SchoolAid\Nadota\Mcp\McpServiceProvider;
use SchoolAid\Nadota\Providers\RouteServiceProvider;
use SchoolAid\Nadota\Providers\ServiceBindingServiceProvider;
use SchoolAid\Nadota\Providers\ResourceServiceProvider;

class NadotaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merged before anything reads the config: the routes read nadota.middlewares while booting,
        // and an app that did not publish config/nadota.php must still get the defaults there.
        $this->mergeConfigFrom(__DIR__ . '/../config/nadota.php', 'nadota');

        $this->app->register(ServiceBindingServiceProvider::class);
        $this->app->register(McpServiceProvider::class);
    }

    public function boot(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->register(ResourceServiceProvider::class);
    }
}
