<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

/**
 * A form-only field whose value is never persisted.
 *
 * Its options come from a Nadota Resource, and its value exists to be consumed
 * by another field's scopedBy(), narrowing that field's options.
 *
 * Lookup fields still take part in validation: marking one ->required() will
 * make store/update require the value even though nothing is written with it.
 */
class Lookup extends Field
{
    public function __construct(string $label, string $attribute)
    {
        parent::__construct(
            $label,
            $attribute,
            FieldType::LOOKUP->value,
            static::safeConfig('nadota.fields.lookup.component', 'FieldLookup')
        );

        $this->virtual();
        $this->onlyOnForms();
    }

    /**
     * Lookup fields hold no model state.
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        return null;
    }

    /**
     * Lookup fields are never persisted. The value is read straight from the
     * request by the option scopes that depend on it.
     */
    public function fill(Request $request, Model $model): void
    {
        // Intentionally empty.
    }
}
