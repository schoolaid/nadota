<?php

namespace SchoolAid\Nadota\Providers;

use Illuminate\Support\ServiceProvider;
use SchoolAid\Nadota\Contracts\MenuServiceInterface;
use SchoolAid\Nadota\Contracts\ResourceAuthorizationInterface;
use SchoolAid\Nadota\Contracts\ResourceIndexInterface;
use SchoolAid\Nadota\Contracts\ResourceCreateInterface;
use SchoolAid\Nadota\Contracts\ResourceStoreInterface;
use SchoolAid\Nadota\Contracts\ResourceShowInterface;
use SchoolAid\Nadota\Contracts\ResourceEditInterface;
use SchoolAid\Nadota\Contracts\ResourceUpdateInterface;
use SchoolAid\Nadota\Contracts\ResourceDestroyInterface;
use SchoolAid\Nadota\Contracts\ResourceForceDeleteInterface;
use SchoolAid\Nadota\Contracts\ResourceRestoreInterface;
use SchoolAid\Nadota\Http\Services\MenuService;
use SchoolAid\Nadota\Http\Services\ResourceAuthorizationService;
use SchoolAid\Nadota\Http\Services\ResourceIndexService;
use SchoolAid\Nadota\Http\Services\ResourceCreateService;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceShowService;
use SchoolAid\Nadota\Http\Services\ResourceEditService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Http\Services\ResourceDestroyService;
use SchoolAid\Nadota\Http\Services\ResourceForceDeleteService;
use SchoolAid\Nadota\Http\Services\ResourceRestoreService;

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
        $this->app->singleton(ResourceForceDeleteInterface::class, ResourceForceDeleteService::class);
        $this->app->singleton(ResourceRestoreInterface::class, ResourceRestoreService::class);
    }
}
