<?php

namespace SchoolAid\Nadota\Mcp\Contracts;

/**
 * The allowlist the MCP read tools serve: resource key => entry. A resource that is not in the
 * list does not exist for the tools, and a field that is not in an entry's `fields` never leaves
 * them, whatever the panel shows.
 *
 * The default implementation reads `config('nadota.mcp.resources')`. An app that serves several
 * MCP servers from one codebase (a tenant server and a central one, say) binds its own.
 *
 * Entry shape:
 * - fields: Nadota field keys (`Field::key()`; for a BelongsTo it is `snake(relation)_id`).
 * - money: attribute => currency source: `'default'` (the CurrencyResolver), a column of the
 *   record (an empty value falls back to the default) or `['amount' => method, 'currency' =>
 *   method]` on the model.
 * - search: expose the resource's search (default false).
 * - hints: attribute => a short text for the agent (what the value means, what not to sum).
 * Any other key is for the app's ExposureRules.
 */
interface ResourceList
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function entries(): array;
}
