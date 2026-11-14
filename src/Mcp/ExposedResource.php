<?php

namespace SchoolAid\Nadota\Mcp;

use Illuminate\Support\Collection;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;
use SchoolAid\Nadota\Resource;

/**
 * A Nadota resource that passed ExposedResources, with its ResourceList entry.
 */
class ExposedResource
{
    /**
     * @param  array<int, string>  $fields
     * @param  array<string, string|array{amount: string, currency: string}>  $money
     * @param  array<string, string>  $hints  attribute => text for the agent (what the value means)
     * @param  array<string, mixed>  $entry  the raw entry, for app-specific keys
     */
    public function __construct(
        public readonly string $key,
        public readonly Resource $resource,
        public readonly array $fields,
        public readonly array $money,
        public readonly bool $search,
        public readonly array $hints = [],
        public readonly array $entry = [],
    ) {
    }

    public function lists(string $fieldKey): bool
    {
        return in_array($fieldKey, $this->fields, true);
    }

    /**
     * `money` attributes that are not fields of the resource: they come out as an extra column.
     * A Hidden field does not count as a field: it is a column the resource loads for its own
     * use (e.g. an amount its show page reads), and as a type it is blocked.
     *
     * @param  Collection<int, Field>  $fields  every field of the resource (flattenFields)
     * @return array<int, string>
     */
    public function extraMoneyAttributes(Collection $fields): array
    {
        $fieldKeys = $fields
            ->reject(fn (Field $field): bool => $field->getType() === FieldType::HIDDEN->value)
            ->map(fn (Field $field): string => $field->key())
            ->all();

        return array_values(array_diff(array_keys($this->money), $fieldKeys));
    }

    public function usesComputedMoney(): bool
    {
        return collect($this->money)->contains(fn (mixed $source): bool => is_array($source));
    }

    /**
     * Columns the projection reads even when Nadota's select does not bring them: the column
     * amounts and their currencies.
     *
     * @return array<int, string>
     */
    public function moneyColumns(): array
    {
        return collect($this->money)
            ->filter(fn (mixed $source): bool => is_string($source))
            ->flatMap(fn (string $source, string $attribute): array => $source === 'default' ? [$attribute] : [$attribute, $source])
            ->unique()
            ->values()
            ->all();
    }
}
