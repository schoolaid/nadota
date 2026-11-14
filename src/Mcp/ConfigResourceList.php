<?php

namespace SchoolAid\Nadota\Mcp;

use SchoolAid\Nadota\Mcp\Contracts\ResourceList;

/**
 * The allowlist from `config('nadota.mcp.resources')`. Empty by default: nothing is exposed
 * until an app lists it.
 */
class ConfigResourceList implements ResourceList
{
    public function entries(): array
    {
        return (array) config('nadota.mcp.resources', []);
    }
}
