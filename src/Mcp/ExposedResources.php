<?php

namespace SchoolAid\Nadota\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Mcp\Contracts\ExposureRule;
use SchoolAid\Nadota\Mcp\Contracts\ResourceList;
use SchoolAid\Nadota\Mcp\Rules\RequiresExplicitPolicy;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\ResourceManager;

/**
 * The gatekeeper of the MCP read tools. A resource passes only if it exists in Nadota, is in the
 * ResourceList, meets every ExposureRule (RequiresExplicitPolicy first, then the app's) and the
 * Gate allows `viewAny` to the user. Every other case is null, without saying which check failed:
 * the tools answer "not found or no access" either way.
 *
 * Bound `scoped`: visibleTo() is memoized for one request or job (Octane and queue workers flush
 * it between them).
 */
class ExposedResources
{
    /**
     * @var array<string, array<int, ExposedResource>>
     */
    private array $visible = [];

    public function __construct(
        private readonly ResourceList $list,
        private readonly FieldTypes $types,
    ) {
    }

    public function resolve(?Authenticatable $user, string $key): ?ExposedResource
    {
        $entry = $this->entries()[$key] ?? null;

        if ($user === null || ! is_array($entry) || ! ResourceManager::exists($key)) {
            return null;
        }

        $resource = $this->instantiate($key);

        if ($this->rejection($resource, $entry) !== null || ! Gate::forUser($user)->allows('viewAny', $resource->model)) {
            return null;
        }

        return new ExposedResource(
            key: $key,
            resource: $resource,
            fields: array_values(array_filter(
                (array) ($entry['fields'] ?? []),
                fn (mixed $field): bool => is_string($field) && ! $this->types->isForbidden($field),
            )),
            money: array_filter(
                (array) ($entry['money'] ?? []),
                fn (mixed $source, mixed $attribute): bool => is_string($attribute) && ! $this->types->isForbidden($attribute),
                ARRAY_FILTER_USE_BOTH,
            ),
            search: (bool) ($entry['search'] ?? false),
            hints: (array) ($entry['hints'] ?? []),
            entry: $entry,
        );
    }

    /**
     * @return array<int, ExposedResource>
     */
    public function visibleTo(?Authenticatable $user): array
    {
        if ($user === null) {
            return [];
        }

        $memoKey = $user::class.'|'.$user->getAuthIdentifier().'|'.md5(serialize([$this->entries(), $this->ruleClasses()]));

        return $this->visible[$memoKey] ??= collect(array_keys($this->entries()))
            ->map(fn (string $key): ?ExposedResource => $this->resolve($user, $key))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Why a listed resource is not exposed to anyone, or null when every rule passes. The Gate
     * (a per-user decision) is not part of it.
     *
     * @param  array<string, mixed>  $entry
     */
    public function rejection(Resource $resource, array $entry): ?string
    {
        foreach ($this->rules() as $rule) {
            if (($reason = $rule->check($resource, $entry)) !== null) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function entries(): array
    {
        return $this->list->entries();
    }

    public function instantiate(string $key): Resource
    {
        return app(ResourceManager::getResources()[$key]['class']);
    }

    /**
     * @return array<int, ExposureRule>
     */
    private function rules(): array
    {
        return array_map(fn (string $class): ExposureRule => app($class), [RequiresExplicitPolicy::class, ...$this->ruleClasses()]);
    }

    /**
     * @return array<int, class-string<ExposureRule>>
     */
    private function ruleClasses(): array
    {
        return array_values((array) config('nadota.mcp.rules', []));
    }
}
