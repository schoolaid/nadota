<?php

namespace SchoolAid\Nadota\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\ResourceManager;

/**
 * The guard of the ResourceList. An app calls it from one test so that renaming a field, listing
 * a resource whose policy is missing, or declaring an amount without a currency turns the suite
 * red instead of silently shrinking (or widening) what the agent sees:
 *
 *     expect(app(ResourceListValidator::class)->problems($user))->toBe([]);
 *
 * $user is whoever the fields are resolved for: a user who sees every field (an admin) catches
 * the most. The column checks need the tables; pass `checkColumns: false` where the test has no
 * schema.
 */
class ResourceListValidator
{
    public function __construct(
        private readonly ExposedResources $resources,
        private readonly FieldTypes $types,
        private readonly QueryCatalog $catalog,
        private readonly RequestScope $scope,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function problems(Authenticatable $user, bool $checkColumns = true): array
    {
        $problems = [];

        foreach ($this->resources->entries() as $key => $entry) {
            if (! is_array($entry)) {
                $problems[] = "{$key}: the entry is not an array";

                continue;
            }

            if (! ResourceManager::exists($key)) {
                $problems[] = "{$key}: not a Nadota resource";

                continue;
            }

            $resource = $this->resources->instantiate($key);

            if (($reason = $this->resources->rejection($resource, $entry)) !== null) {
                $problems[] = "{$key}: {$reason}";
            }

            $fields = $this->scope->run($user, $resource, [], fn (NadotaRequest $request): Collection => $resource
                ->flattenFields($request)
                ->keyBy(fn (Field $field): string => $field->key()));

            $problems = [
                ...$problems,
                ...$this->fieldProblems($key, $entry, $fields),
                ...$this->moneyProblems($key, $entry, $fields, new ($resource->model), $checkColumns),
                ...$this->searchProblems($key, $entry, $fields, $resource),
                ...$this->hintProblems($key, $entry),
                ...($checkColumns ? $this->exactStatusProblems($key, $entry, $fields, new ($resource->model)) : []),
            ];
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  Collection<string, Field>  $fields
     * @return array<int, string>
     */
    private function fieldProblems(string $key, array $entry, Collection $fields): array
    {
        $listed = (array) ($entry['fields'] ?? []);
        $money = (array) ($entry['money'] ?? []);
        $problems = $listed === [] ? ["{$key}: lists no fields"] : [];

        foreach ($listed as $fieldKey) {
            $field = $fields->get($fieldKey);

            if ($field === null) {
                $problems[] = "{$key}.{$fieldKey}: not a field of the resource";

                continue;
            }

            if ($this->types->isForbidden($field->getAttribute()) || $this->types->isForbidden($field->key())) {
                $problems[] = "{$key}.{$fieldKey}: its attribute is forbidden";
            } elseif ($this->types->of($field) === null) {
                $problems[] = "{$key}.{$fieldKey}: the type {$field->getType()} is blocked";
            }

            if ($field->getType() === FieldType::CURRENCY->value && ! array_key_exists($fieldKey, $money)) {
                $problems[] = "{$key}.{$fieldKey}: is Currency and has no currency in money";
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  Collection<string, Field>  $fields
     * @return array<int, string>
     */
    private function moneyProblems(string $key, array $entry, Collection $fields, object $model, bool $checkColumns): array
    {
        $listed = (array) ($entry['fields'] ?? []);
        $problems = [];
        $schema = $checkColumns ? $model->getConnection()->getSchemaBuilder() : null;

        foreach ((array) ($entry['money'] ?? []) as $attribute => $source) {
            if ($this->types->isForbidden((string) $attribute)) {
                $problems[] = "{$key}.{$attribute}: its attribute is forbidden";

                continue;
            }

            $isVisibleField = $fields->has($attribute) && $fields->get($attribute)->getType() !== FieldType::HIDDEN->value;

            if ($isVisibleField && ! in_array($attribute, $listed, true)) {
                $problems[] = "{$key}.{$attribute}: is in money but not in fields";
            }

            if (is_array($source)) {
                foreach (['amount', 'currency'] as $part) {
                    if (! method_exists($model, (string) ($source[$part] ?? ''))) {
                        $problems[] = "{$key}.{$attribute}: the {$part} method does not exist on the model";
                    }
                }

                continue;
            }

            if (! is_string($source)) {
                $problems[] = "{$key}.{$attribute}: the currency source must be 'default', a column or ['amount' => method, 'currency' => method]";

                continue;
            }

            if ($schema === null) {
                continue;
            }

            if (! $fields->has($attribute) && ! $schema->hasColumn($model->getTable(), $attribute)) {
                $problems[] = "{$key}.{$attribute}: neither a field nor a column";
            }

            if ($source !== 'default' && ! $schema->hasColumn($model->getTable(), $source)) {
                $problems[] = "{$key}.{$attribute}: the currency column {$source} does not exist";
            }
        }

        return $problems;
    }

    /**
     * With `search: true`, every direct searchable attribute must be among the listed fields:
     * otherwise a search would match on a column the agent cannot see. Relation searches and
     * applySearch are accepted, as the panel offers the same search to the same user.
     *
     * @param  array<string, mixed>  $entry
     * @param  Collection<string, Field>  $fields
     * @return array<int, string>
     */
    private function searchProblems(string $key, array $entry, Collection $fields, object $resource): array
    {
        if (! ($entry['search'] ?? false)) {
            return [];
        }

        if (! $resource->isSearchable()) {
            return ["{$key}: search is true but the resource has no search"];
        }

        /*
         * The primary key always leaves as `id`, so searching it reveals nothing.
         */
        $listedAttributes = collect((array) ($entry['fields'] ?? []))
            ->map(fn (string $fieldKey): ?string => $fields->get($fieldKey)?->getAttribute())
            ->filter()
            ->push((new ($resource->model))->getKeyName())
            ->all();

        return collect($resource->getSearchableAttributes())
            ->reject(fn (string $attribute): bool => in_array($attribute, $listedAttributes, true))
            ->map(fn (string $attribute): string => "{$key}: searches {$attribute}, which is not in fields")
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<int, string>
     */
    private function hintProblems(string $key, array $entry): array
    {
        $listed = (array) ($entry['fields'] ?? []);
        $money = (array) ($entry['money'] ?? []);

        return collect(array_keys((array) ($entry['hints'] ?? [])))
            ->reject(fn (string $attribute): bool => in_array($attribute, $listed, true) || array_key_exists($attribute, $money))
            ->map(fn (string $attribute): string => "{$key}.{$attribute}: hint of a field that is not listed")
            ->values()
            ->all();
    }

    /**
     * The exact Status filter runs a whereIn on the field's attribute, so it must be a column.
     *
     * @param  array<string, mixed>  $entry
     * @param  Collection<string, Field>  $fields
     * @return array<int, string>
     */
    private function exactStatusProblems(string $key, array $entry, Collection $fields, object $model): array
    {
        $schema = $model->getConnection()->getSchemaBuilder();
        $problems = [];

        foreach ((array) ($entry['fields'] ?? []) as $fieldKey) {
            $field = $fields->get($fieldKey);

            if ($field === null || ! $field->isFilterable()) {
                continue;
            }

            foreach ($field->filters() as $filter) {
                if ($this->catalog->isExactStatus($field, $filter) && ! $schema->hasColumn($model->getTable(), $field->getAttribute())) {
                    $problems[] = "{$key}.{$fieldKey}: the exact Status filter runs on {$field->getAttribute()}, which is not a column";
                }
            }
        }

        return $problems;
    }
}
