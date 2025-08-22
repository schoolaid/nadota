<?php

namespace Said\Nadota\Providers;

use Illuminate\Support\ServiceProvider;
use Said\Nadota\Contracts\MenuServiceInterface;
use Said\Nadota\Contracts\ResourceAuthorizationInterface;
use Said\Nadota\Contracts\ResourceIndexInterface;
use Said\Nadota\Contracts\ResourceCreateInterface;
use Said\Nadota\Contracts\ResourceStoreInterface;
use Said\Nadota\Contracts\ResourceShowInterface;
use Said\Nadota\Contracts\ResourceEditInterface;
use Said\Nadota\Contracts\ResourceUpdateInterface;
use Said\Nadota\Contracts\ResourceDestroyInterface;
use Said\Nadota\Http\Services\MenuService;
use Said\Nadota\Http\Services\ResourceAuthorizationService;
use Said\Nadota\Http\Services\ResourceIndexService;
use Said\Nadota\Http\Services\ResourceCreateService;
use Said\Nadota\Http\Services\ResourceStoreService;
use Said\Nadota\Http\Services\ResourceShowService;
use Said\Nadota\Http\Services\ResourceEditService;
use Said\Nadota\Http\Services\ResourceUpdateService;
use Said\Nadota\Http\Services\ResourceDestroyService;

class ServiceBindingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResourceAuthorizationInterface::class, ResourceAuthorizationService::class);
        $this->app->singleton(MenuServiceInterface::class, MenuService::class);
        $this->app->singleton(ResourceIndexInterface::class, ResourceIndexService::class);
        $this->app->singleton(ResourceCreateInterface::class, ResourceCreateService::class);
        $this->app->singleton(ResourceStoreInterface::class, ResourceStoreService::class);
        $this->app->singleton(ResourceShowInterface::class, ResourceShowService::class);
        $this->app->singleton(ResourceEditInterface::class, ResourceEditService::class);
        $this->app->singleton(ResourceUpdateInterface::class, ResourceUpdateService::class);
        $this->app->singleton(ResourceDestroyInterface::class, ResourceDestroyService::class);
    }
}
