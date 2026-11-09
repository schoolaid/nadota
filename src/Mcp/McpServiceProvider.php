<?php

namespace SchoolAid\Nadota\Mcp;

use Illuminate\Support\ServiceProvider;
use SchoolAid\Nadota\Mcp\Contracts\CurrencyResolver;
use SchoolAid\Nadota\Mcp\Contracts\ResourceList;

/**
 * Bindings of the MCP read tools. The defaults read `config('nadota.mcp')`; an app binds its own
 * ResourceList or CurrencyResolver and keeps them (bindIf). ExposedResources is `scoped`, so its
 * visibleTo() memo lives one request or job.
 */
class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf(ResourceList::class, ConfigResourceList::class);
        $this->app->bindIf(CurrencyResolver::class, ConfigCurrencyResolver::class);
        $this->app->scoped(ExposedResources::class);
    }
}
