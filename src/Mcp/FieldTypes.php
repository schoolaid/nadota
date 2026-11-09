<?php

namespace SchoolAid\Nadota\Mcp;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Field;

/**
 * The field types that may leave the MCP tools, and their type on the MCP side. Any type outside
 * the map is blocked even if an entry names it: blobs (File, Image, Json, Code, KeyValue…),
 * credentials (Password, Signature), panel components (CustomComponent, Html), to-many relations
 * (HasMany, BelongsToMany, MorphMany) and any type Nadota adds later.
 *
 * A field whose attribute is a forbidden name (see forbiddenAttributes()) is blocked the same way,
 * whatever its type: a secret stored in a plain Input column must not leave either.
 */
class FieldTypes
{
    public function __construct(private readonly Labels $labels = new Labels)
    {
    }

    private const TYPES = [
        FieldType::TEXT->value => 'string',
        FieldType::TEXTAREA->value => 'string',
        FieldType::EMAIL->value => 'string',
        FieldType::URL->value => 'string',
        FieldType::NUMBER->value => 'number',
        FieldType::CURRENCY->value => 'number',
        FieldType::CHECKBOX->value => 'boolean',
        FieldType::BOOLEAN->value => 'boolean',
        FieldType::DATE->value => 'date',
        FieldType::DATETIME->value => 'datetime',
        FieldType::TIME->value => 'time',
        FieldType::SELECT->value => 'enum',
        FieldType::RADIO->value => 'enum',
        FieldType::STATUS->value => 'enum',
        FieldType::BELONGS_TO->value => 'relation',
        FieldType::MORPH_TO->value => 'relation',
        FieldType::HAS_ONE->value => 'relation',
        FieldType::MORPH_ONE->value => 'relation',
    ];

    /**
     * Attribute names that never leave the tools. An app adds its own in
     * `config('nadota.mcp.forbidden_attributes')`; it cannot remove these.
     */
    private const FORBIDDEN_ATTRIBUTES = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_key',
        'api_secret',
        'api_token',
        'access_token',
        'refresh_token',
        'client_secret',
        'secret',
        'private_key',
    ];

    /**
     * The field's MCP type, or null if its type or its attribute is blocked.
     */
    public function of(Field $field): ?string
    {
        if ($this->isForbidden($field->getAttribute()) || $this->isForbidden($field->key())) {
            return null;
        }

        return self::TYPES[$field->getType()] ?? null;
    }

    /**
     * Like of(), but an attribute declared in `money` is always `money`.
     */
    public function forColumn(ExposedResource $exposed, Field $field): ?string
    {
        $type = $this->of($field);

        if ($type === null) {
            return null;
        }

        return array_key_exists($field->key(), $exposed->money) ? 'money' : $type;
    }

    /**
     * An enum's options (Select, Status) with their translated labels.
     *
     * @return array<int, array{value: mixed, label: string}>
     */
    public function options(Field $field): array
    {
        return collect($field->getOptions())
            ->filter(fn (mixed $option): bool => is_array($option) && array_key_exists('value', $option))
            ->map(fn (array $option): array => [
                'value' => $option['value'],
                'label' => $this->labels->translate((string) ($option['label'] ?? $option['value'])),
            ])
            ->values()
            ->all();
    }

    public function isForbidden(?string $attribute): bool
    {
        return $attribute !== null && in_array(strtolower($attribute), $this->forbiddenAttributes(), true);
    }

    /**
     * @return array<int, string>
     */
    public function forbiddenAttributes(): array
    {
        $extra = array_map(
            fn (mixed $attribute): string => strtolower((string) $attribute),
            (array) config('nadota.mcp.forbidden_attributes', []),
        );

        return array_values(array_unique([...self::FORBIDDEN_ATTRIBUTES, ...$extra]));
    }
}
