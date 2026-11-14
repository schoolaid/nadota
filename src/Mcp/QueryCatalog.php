<?php

namespace SchoolAid\Nadota\Mcp;

use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Filters\BooleanFilter;
use SchoolAid\Nadota\Http\Filters\DateFilter;
use SchoolAid\Nadota\Http\Filters\DefaultFilter;
use SchoolAid\Nadota\Http\Filters\DynamicSelectFilter;
use SchoolAid\Nadota\Http\Filters\Filter;
use SchoolAid\Nadota\Http\Filters\NumberFilter;
use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * What an agent may ask query-admin-resource for, and how describe-admin-resource tells it.
 * Filters and sorting only on listed fields the user can see (filtering by a hidden field would
 * let it guess the value), search only with `search: true`. Anything else is an error: Nadota
 * would ignore it silently and the agent would believe it filtered.
 *
 * Filters come from every listed field, including the detail-only ones: Nadota's
 * ApplyFiltersPipe only takes index fields, and without them an agent could not ask for the
 * children of a parent whose foreign key only shows on the detail page.
 */
class QueryCatalog
{
    /**
     * Exact Nadota filter class => kind. Of the range filters only the date one is exposed
     * (`dateRange`); any other class, subclasses included, is not exposed.
     */
    private const FILTER_KINDS = [
        DefaultFilter::class => 'contains',
        SelectFilter::class => 'oneOf',
        DynamicSelectFilter::class => 'relationId',
        BooleanFilter::class => 'boolean',
        NumberFilter::class => 'number',
        DateFilter::class => 'date',
    ];

    public function __construct(
        private readonly FieldTypes $types,
        private readonly Labels $labels,
    ) {
    }

    /**
     * Listed fields, in the resource's order, that are not blocked and that the user sees on the
     * index or on the detail page.
     *
     * @return Collection<int, Field>
     */
    public function listedFields(ExposedResource $exposed, NadotaRequest $request): Collection
    {
        return $exposed->resource->flattenFields($request)
            ->filter(fn (Field $field): bool => $exposed->lists($field->key())
                && $this->types->of($field) !== null
                && ($field->isShowOnIndex($request) || $field->isShowOnDetail($request)))
            ->values();
    }

    /**
     * @return array<string, array{filter: Filter, field: Field, kind: string}>
     */
    public function filters(ExposedResource $exposed, NadotaRequest $request): array
    {
        $catalog = [];

        foreach ($this->listedFields($exposed, $request) as $field) {
            if (! $field->isFilterable()) {
                continue;
            }

            foreach ($field->filters() as $filter) {
                $kind = $this->kindOf($field, $filter);

                if ($kind !== null) {
                    $catalog[$filter->key()] = ['filter' => $filter, 'field' => $field, 'kind' => $kind];
                }
            }
        }

        return $catalog;
    }

    /**
     * The kind a filter is exposed with, or null if it is not exposed. A date range is
     * `dateRange`; other ranges are not exposed.
     */
    private function kindOf(Field $field, Filter $filter): ?string
    {
        /*
         * Nadota gives a Time field a DateFilter, which compares a date with the time column:
         * the agent would send a date and filter nothing.
         */
        if ($field->getType() === FieldType::TIME->value) {
            return null;
        }

        if ($this->isExactStatus($field, $filter)) {
            return 'oneOf';
        }

        $kind = self::FILTER_KINDS[$filter::class] ?? null;

        if ($kind === null || ! $filter->isRange()) {
            return $kind;
        }

        return $kind === 'date' ? 'dateRange' : null;
    }

    /**
     * Valid `sortField` keys: listed index fields Nadota can sort (ApplySortingPipe only looks
     * among the index fields).
     *
     * @return array<int, string>
     */
    public function sortableKeys(ExposedResource $exposed, NadotaRequest $request): array
    {
        return $exposed->resource->fieldsForIndex($request)
            ->filter(fn (Field $field): bool => $exposed->lists($field->key())
                && $this->types->of($field) !== null
                && $field->isSortable())
            ->map(fn (Field $field): string => $field->key())
            ->values()
            ->all();
    }

    public function searchable(ExposedResource $exposed): bool
    {
        return $exposed->search && $exposed->resource->isSearchable();
    }

    /**
     * Why the query is rejected, or null if everything asked for is in the catalog.
     *
     * @param  array<string, mixed>  $filters
     */
    public function rejection(ExposedResource $exposed, NadotaRequest $request, ?string $search, array $filters, ?string $sortField): ?string
    {
        if ($search !== null && $search !== '' && ! $this->searchable($exposed)) {
            return "{$exposed->key} no tiene búsqueda; usa filters (ver describe-admin-resource).";
        }

        $catalog = $this->filters($exposed, $request);

        foreach ($filters as $key => $value) {
            $entry = $catalog[$key] ?? null;

            if ($entry === null) {
                $valid = $catalog === [] ? 'ninguno' : implode(', ', array_keys($catalog));

                return "Filtro desconocido en {$exposed->key}: {$key}. Válidos: {$valid}.";
            }

            if (! $this->acceptsValue($entry, $value)) {
                return "Valor inválido para el filtro {$key} ({$entry['kind']}): {$this->expectedValue($entry)}.";
            }
        }

        if ($sortField !== null) {
            $sortable = $this->sortableKeys($exposed, $request);

            if (! in_array($sortField, $sortable, true)) {
                $valid = $sortable === [] ? 'ninguno' : implode(', ', $sortable);

                return "sortField inválido en {$exposed->key}: {$sortField}. Válidos: {$valid}.";
            }
        }

        return null;
    }

    /**
     * The resource's shape for describe-admin-resource.
     *
     * @return array{key: string, title: string, fields: array<int, array<string, mixed>>, filters: array<int, array<string, mixed>>, search: array{enabled: bool, fields: array<int, string>}, defaultSort: array<string, string>|null}
     */
    public function describe(ExposedResource $exposed, NadotaRequest $request): array
    {
        $resource = $exposed->resource;
        $listed = $this->listedFields($exposed, $request);
        $sortable = $this->sortableKeys($exposed, $request);
        $withoutNulls = fn (mixed $value): bool => $value !== null;

        $fields = $listed->map(fn (Field $field): array => array_filter([
            'key' => $field->key(),
            'label' => $this->labels->translate($field->getLabel()),
            'type' => $this->types->forColumn($exposed, $field),
            'sortable' => in_array($field->key(), $sortable, true),
            'onIndex' => $field->isShowOnIndex($request),
            'onDetail' => $field->isShowOnDetail($request),
            'options' => $this->types->of($field) === 'enum' ? $this->types->options($field) : null,
            'hint' => $exposed->hints[$field->key()] ?? null,
        ], $withoutNulls))->values();

        foreach ($exposed->extraMoneyAttributes($resource->flattenFields($request)) as $attribute) {
            $fields->push(array_filter([
                'key' => $attribute,
                'label' => Str::headline($attribute),
                'type' => 'money',
                'sortable' => false,
                'onIndex' => true,
                'onDetail' => true,
                'hint' => $exposed->hints[$attribute] ?? null,
            ], $withoutNulls));
        }

        $filters = collect($this->filters($exposed, $request))
            ->map(fn (array $entry, string $key): array => array_filter([
                'key' => $key,
                'label' => $this->labels->translate((string) $entry['filter']->name()),
                'kind' => $entry['kind'],
                'options' => $this->types->of($entry['field']) === 'enum' ? $this->types->options($entry['field']) : null,
            ], $withoutNulls))
            ->values()
            ->all();

        $searchFields = $this->searchable($exposed)
            ? $listed
                ->filter(fn (Field $field): bool => in_array($field->getAttribute(), $resource->getSearchableAttributes(), true))
                ->map(fn (Field $field): string => $this->labels->translate($field->getLabel()))
                ->values()
                ->all()
            : [];

        return [
            'key' => $exposed->key,
            'title' => $this->labels->translate((string) $resource->title()),
            'fields' => $fields->all(),
            'filters' => $filters,
            'search' => ['enabled' => $this->searchable($exposed), 'fields' => $searchFields],
            'defaultSort' => $this->defaultSort($exposed),
        ];
    }

    /**
     * Applies a catalog filter. A `relationId` on a BelongsTo compares the row's foreign key:
     * Nadota's DynamicSelectFilter uses whereHas, which applies the related model's global scopes
     * (SoftDeletes, tenancy) and hides the rows whose related record is deleted or out of reach,
     * precisely the ones the agent asks to see. It filters nothing the unfiltered query would not
     * already return.
     *
     * @param  array{filter: Filter, field: Field, kind: string}  $entry  a value of filters()
     */
    public function applyFilter(array $entry, NadotaRequest $request, Builder $query, mixed $value): Builder
    {
        if ($entry['kind'] === 'relationId' && $entry['field'] instanceof BelongsTo) {
            $foreignKey = $entry['field']->resolveForeignKeyFromModel($query->getModel()::class);

            return $query->where($query->getModel()->qualifyColumn($foreignKey), (int) $value);
        }

        if ($this->isExactStatus($entry['field'], $entry['filter'])) {
            $values = is_array($value) ? $value : [$value];

            return $query->whereIn($query->getModel()->qualifyColumn($entry['field']->getAttribute()), $values);
        }

        if ($entry['kind'] === 'dateRange') {
            $column = $query->getModel()->qualifyColumn($entry['field']->getAttribute());

            return $query
                ->when($value['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate($column, '>=', $from))
                ->when($value['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate($column, '<=', $to));
        }

        return $entry['filter']->apply($request, $query, $value);
    }

    /**
     * A `Status` with options gets a DefaultFilter (`LIKE`) from Nadota: `proc` matched
     * `processed` and lists were not accepted. Here it is exposed as `oneOf` and applied with
     * `whereIn` on the column; the panel keeps Nadota's filter.
     */
    public function isExactStatus(Field $field, Filter $filter): bool
    {
        return $field->getType() === FieldType::STATUS->value
            && $filter::class === DefaultFilter::class
            && $this->types->options($field) !== [];
    }

    /**
     * Accepts only what Nadota's filter applies as given: Nadota ignores the rest (boolean,
     * relationId, date, an empty value) or turns it into an empty set (an invalid date or
     * number), and the agent would believe it filtered.
     *
     * @param  array{filter: Filter, field: Field, kind: string}  $entry
     */
    private function acceptsValue(array $entry, mixed $value): bool
    {
        return match ($entry['kind']) {
            'contains' => (is_string($value) && trim($value) !== '') || is_int($value) || is_float($value),
            'oneOf' => $this->acceptsOneOf($entry, $value),
            'relationId' => ! is_bool($value) && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false,
            'number' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)),
            'date' => is_string($value) && $this->isRealDate($value),
            'dateRange' => $this->acceptsDateRange($value),
            'boolean' => in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true),
            default => false,
        };
    }

    /**
     * A scalar (not a boolean) or a non-empty list of them; if the field is an enum with options,
     * each value must be the `value` of an option.
     *
     * @param  array{filter: Filter, field: Field, kind: string}  $entry
     */
    private function acceptsOneOf(array $entry, mixed $value): bool
    {
        if (is_array($value) && ($value === [] || ! array_is_list($value))) {
            return false;
        }

        $values = is_array($value) ? $value : [$value];

        foreach ($values as $item) {
            if (! is_scalar($item) || is_bool($item)) {
                return false;
            }
        }

        $options = $this->enumOptionValues($entry);

        if ($options === []) {
            return true;
        }

        foreach ($values as $item) {
            if (! in_array((string) $item, $options, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * What the filter expects, for the rejection message.
     *
     * @param  array{filter: Filter, field: Field, kind: string}  $entry
     */
    private function expectedValue(array $entry): string
    {
        if ($entry['kind'] === 'oneOf') {
            $options = $this->enumOptionValues($entry);

            return $options === []
                ? 'un valor simple o una lista de valores simples'
                : 'uno de: '.implode(', ', $options).' (o una lista de ellos)';
        }

        return match ($entry['kind']) {
            'contains' => 'un texto no vacío',
            'relationId' => 'un id entero positivo',
            'number' => 'un número',
            'date' => 'una fecha YYYY-MM-DD',
            'dateRange' => 'un objeto {"from": "YYYY-MM-DD", "to": "YYYY-MM-DD"} con una o las dos fechas (inclusivas), from <= to',
            'boolean' => 'true o false',
            default => 'un valor simple',
        };
    }

    /**
     * The values of the field's options when it is an enum, as text; empty if it is not or
     * declares no options.
     *
     * @param  array{filter: Filter, field: Field, kind: string}  $entry
     * @return array<int, string>
     */
    private function enumOptionValues(array $entry): array
    {
        if ($this->types->of($entry['field']) !== 'enum') {
            return [];
        }

        return collect($this->types->options($entry['field']))
            ->pluck('value')
            ->filter(fn (mixed $optionValue): bool => is_scalar($optionValue))
            ->map(fn (mixed $optionValue): string => (string) $optionValue)
            ->values()
            ->all();
    }

    /**
     * The order Nadota's index applies when no `sortField` arrives: the resource's `defaultSort`
     * or, if the model uses timestamps, created_at desc.
     *
     * @return array<string, string>|null
     */
    private function defaultSort(ExposedResource $exposed): ?array
    {
        $defaultSort = $exposed->resource->getDefaultSort();

        if (! empty($defaultSort)) {
            return $defaultSort;
        }

        $model = new $exposed->resource->model;

        return $model->usesTimestamps()
            ? ['field' => $model->getCreatedAtColumn(), 'direction' => 'desc']
            : null;
    }

    /**
     * `{from, to}` with at least one of them, each a real date and from <= to. Nadota's
     * DateFilter compares the text with whereBetween and would leave out the last day of a
     * `created_at` with a time; that is why the catalog applies the range itself, whole days.
     */
    private function acceptsDateRange(mixed $value): bool
    {
        if (! is_array($value) || array_is_list($value) || array_diff(array_keys($value), ['from', 'to']) !== []) {
            return false;
        }

        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        if ($from === null && $to === null) {
            return false;
        }

        foreach ([$from, $to] as $date) {
            if ($date !== null && (! is_string($date) || ! $this->isRealDate($date))) {
                return false;
            }
        }

        return $from === null || $to === null || $from <= $to;
    }

    private function isRealDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
