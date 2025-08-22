<?php

namespace Said\Nadota\Providers;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Said\Nadota\ResourceManager;

class ResourceServiceProvider extends ServiceProvider
{
    /**
     * @throws Exception
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }

        if (! $this->app->configurationIsCached()) {
            $this->mergeConfigFrom(__DIR__ . '/../../config/nadota.php', 'nadota');
        }

        $this->registerResources();
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../../Console/stubs/NadotaServiceProvider.stub' => app_path('Providers/NadotaServiceProvider.php'),
        ], 'nadota-provider');

        $this->publishes([
            __DIR__ . '/../../config/nadota.php' => config_path('nadota.php'),
        ], 'nadota-config');
    }

    /**
     * @throws Exception
     */
    protected function registerResources(): void
    {
        $path = Config::get('nadota.path_resources');

        if(config('app.env') == 'production') {
            if (!Cache::has(config('nadota.key_resources_cache'))) {
                ResourceManager::registerResource($path);
            }
        }

        ResourceManager::registerResource($path);
    }
}
