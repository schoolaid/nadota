<?php

namespace SchoolAid\Nadota\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use SchoolAid\Nadota\Contracts\ResourceInterface;

class RelationResource
{
    protected Collection $fields;
    protected ?ResourceInterface $resource;
    protected ?array $exceptFieldKeys;
    protected ?\Closure $labelResolver;
    protected bool $includePermissions;

    public function __construct(
        Collection $fields,
        ?ResourceInterface $resource = null,
        ?array $exceptFieldKeys = null,
        ?\Closure $labelResolver = null,
        bool $includePermissions = true
    ) {
        $this->fields = $this->filterFields($fields, $exceptFieldKeys);
        $this->resource = $resource;
        $this->exceptFieldKeys = $exceptFieldKeys;
        $this->labelResolver = $labelResolver;
        $this->includePermissions = $includePermissions;
    }

    /**
     * Filter out excluded fields.
     */
    protected function filterFields(Collection $fields, ?array $exceptFieldKeys): Collection
    {
        if ($exceptFieldKeys === null) {
            return $fields;
        }

        return $fields->filter(function ($field) use ($exceptFieldKeys) {
            $key = $field->key();
            $relation = $field->getRelation();

            return !in_array($key, $exceptFieldKeys) &&
                   ($relation === null || !in_array($relation, $exceptFieldKeys));
        });
    }

    /**
     * Format a single model (for BelongsTo, MorphTo).
     */
    public function formatItem(Model $item, Request $request, array $extra = []): array
    {
        $data = [
            'key' => $item->getKey(),
            'label' => $this->resolveLabel($item),
            'resource' => $this->resource ? $this->resource::getKey() : null,
            'fields' => $this->formatFields($item, $request),
        ];

        if ($this->includePermissions && $this->resource) {
            $data['permissions'] = $this->resource->getPermissionsForResource($request, $item);
        }

        return array_merge($data, $extra);
    }

    /**
     * Format a collection of models (for HasMany).
     */
    public function formatCollection(
        Collection $items,
        Request $request,
        array $meta = []
    ): array {
        return [
            'data' => $items->map(fn($item) => $this->formatItem($item, $request))->values()->toArray(),
            'meta' => array_merge([
                'total' => $items->count(),
                'resource' => $this->resource ? $this->resource::getKey() : null,
                'fields' => $this->getFieldsMeta(),
            ], $meta),
        ];
    }

    /**
     * Format fields for a single item.
     */
    protected function formatFields(Model $item, Request $request): array
    {
        return $this->fields->map(function ($field) use ($item, $request) {
            return $field->toArray($request, $item, $this->resource);
        })->values()->toArray();
    }

    /**
     * Get fields metadata.
     */
    protected function getFieldsMeta(): array
    {
        return $this->fields->map(fn($field) => [
            'key' => $field->key(),
            'label' => $field->getLabel(),
            'sortable' => $field->isSortable(),
        ])->values()->toArray();
    }

    /**
     * Resolve the display label for an item.
     */
    protected function resolveLabel(Model $item): mixed
    {
        if ($this->labelResolver) {
            return ($this->labelResolver)($item, $this->resource);
        }

        if ($this->resource && method_exists($this->resource, 'displayLabel')) {
            return $this->resource->displayLabel($item);
        }

        return $item->getKey();
    }

    /**
     * Create a new instance with a label resolver.
     */
    public function withLabelResolver(\Closure $resolver): static
    {
        $this->labelResolver = $resolver;
        return $this;
    }

    /**
     * Create a new instance without permissions.
     */
    public function withoutPermissions(): static
    {
        $this->includePermissions = false;
        return $this;
    }

    /**
     * Static factory method.
     */
    public static function make(
        Collection $fields,
        ?ResourceInterface $resource = null,
        ?array $exceptFieldKeys = null
    ): static {
        return new static($fields, $resource, $exceptFieldKeys);
    }
}
