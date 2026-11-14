<?php

namespace SchoolAid\Nadota\Mcp\Contracts;

use SchoolAid\Nadota\Resource;

/**
 * A structural condition a listed resource must meet before the MCP tools serve it, whoever asks.
 * It runs on every resolution, so a resource that stops meeting it disappears from the tools on
 * the next request, and ResourceListValidator reports it.
 *
 * The package always applies RequiresExplicitPolicy first. An app adds its own rules in
 * `config('nadota.mcp.rules')`, for what its panel relies on and the package cannot know (e.g.
 * that the model carries a tenancy scope, or that its policy is not an allow-all base policy).
 */
interface ExposureRule
{
    /**
     * Why the resource must not be exposed, or null when the rule is met.
     *
     * @param  array<string, mixed>  $entry  the resource's entry in the ResourceList
     */
    public function check(Resource $resource, array $entry): ?string;
}
