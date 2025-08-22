<?php

namespace Said\Nadota\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Middleware\SubstituteBindings;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        });
    }

    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('nadota.prefix', 'nadota-api'),
            'as' => 'nadota.api.',
            'excluded_middleware' => [SubstituteBindings::class],
            'middleware' => config('nadota.middlewares', []),
        ];
    }
}
