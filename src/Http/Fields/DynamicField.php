<?php

namespace SchoolAid\Nadota\Http\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class DynamicField extends Field
{
    /**
     * The field that determines which type to use.
     */
    protected ?string $typeField = null;

    /**
     * Map of type values to Field instances or callbacks.
     * @var array<mixed, Field|Closure>
     */
    protected array $typeMap = [];

    /**
     * Default field to use when no match is found.
     */
    protected ?Field $defaultField = null;

    /**
     * Resolved field for the current model.
     */
    protected ?Field $resolvedField = null;

    /**
     * Whether to include all possible field configs in the response.
     */
    protected bool $includeAllTypes = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct(
            $name,
            $attribute,
            FieldType::DYNAMIC->value,
            static::safeConfig('nadota.fields.dynamic.component', 'FieldDynamic')
        );
    }

    /**
     * Set the field that determines which type to render.
     *
     * @param string $field The attribute name to check
     * @return static
     */
    public function basedOn(string $field): static
    {
        $this->typeField = $field;
        $this->dependsOn($field);

        return $this;
    }

    /**
     * Define the field types mapping.
     *
     * @param array<mixed, Field|Closure> $types Map of value => Field or Closure
     * @return static
     */
    public function types(array $types): static
    {
        $this->typeMap = $types;

        return $this;
    }

    /**
     * Add a single type mapping.
     *
     * @param mixed $value The type value
     * @param Field|Closure $field The field to use or a closure that returns a field
     * @return static
     */
    public function when(mixed $value, Field|Closure $field): static
    {
        $this->typeMap[$value] = $field;

        return $this;
    }

    /**
     * Set the default field when no type matches.
     *
     * @param Field $field
     * @return static
     */
    public function defaultType(Field $field): static
    {
        $this->defaultField = $field;

        return $this;
    }

    /**
     * Only include the matched field config (not all types).
     * This is now the default behavior.
     *
     * @return static
     * @deprecated Use default behavior instead. This method is kept for backwards compatibility.
     */
    public function onlyMatchedType(): static
    {
        $this->includeAllTypes = false;

        return $this;
    }

    /**
     * Include all possible field configs in the response.
     * Useful when the frontend needs to render different field types dynamically.
     *
     * @return static
     */
    public function withAllTypes(): static
    {
        $this->includeAllTypes = true;

        return $this;
    }

    /**
     * Resolve which field to use based on the model or request.
     *
     * @param Request $request
     * @param Model|null $model
     * @return Field|null
     */
    protected function resolveFieldForModel(Request $request, ?Model $model): ?Field
    {
        if ($this->resolvedField !== null) {
            return $this->resolvedField;
        }

        $typeValue = $this->getTypeValue($request, $model);

        if ($typeValue === null) {
            return $this->resolvedField = $this->defaultField;
        }

        // Check if we have a mapping for this type
        if (!isset($this->typeMap[$typeValue])) {
            return $this->resolvedField = $this->defaultField;
        }

        $fieldOrCallback = $this->typeMap[$typeValue];

        // If it's a closure, call it with the model
        if ($fieldOrCallback instanceof Closure) {
            $this->resolvedField = $fieldOrCallback($model, $request);
        } else {
            $this->resolvedField = $fieldOrCallback;
        }

        return $this->resolvedField;
    }

    /**
     * Get the type value from the model or request.
     *
     * @param Request $request
     * @param Model|null $model
     * @return mixed
     */
    protected function getTypeValue(Request $request, ?Model $model): mixed
    {
        if ($this->typeField === null) {
            return null;
        }

        // First try to get from the model
        if ($model !== null) {
            // Support dot notation for relations (e.g., 'formItem.type')
            $value = data_get($model, $this->typeField);
            if ($value !== null) {
                return $this->normalizeTypeValue($value);
            }
        }

        // Fallback to request
        $value = $request->input($this->typeField);

        return $this->normalizeTypeValue($value);
    }

    /**
     * Normalize type value to a scalar that can be used as array key.
     *
     * Handles PHP Enums by extracting their backing value,
     * and Eloquent Models by extracting their primary key.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function normalizeTypeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // Handle backed enums (IntBackedEnum, StringBackedEnum)
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        // Handle unit enums (no backing value) - use the name
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        // Handle Eloquent Models - extract the primary key
        if ($value instanceof Model) {
            return $value->getKey();
        }

        return $value;
    }

    /**
     * Resolve the value for display.
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $field = $this->resolveFieldForModel($request, $model);

        if ($field !== null) {
            // Override the attribute to use this field's attribute
            return $field->resolve($request, $model, $resource);
        }

        // Fallback to raw value
        return $model->{$this->getAttribute()};
    }

    /**
     * Fill the model with the field value.
     */
    public function fill(Request $request, Model $model): void
    {
        // Reset resolved field for fresh resolution
        $this->resolvedField = null;

        $field = $this->resolveFieldForModel($request, $model);

        if ($field !== null) {
            $field->fill($request, $model);
        } else {
            // Default fill behavior
            parent::fill($request, $model);
        }
    }

    /**
     * Get validation rules from the resolved field.
     */
    public function getRules(): array
    {
        // Note: During validation, we may not have the model yet
        // The frontend should handle type-specific validation
        $rules = parent::getRules();

        if ($this->resolvedField !== null) {
            $rules = array_merge($rules, $this->resolvedField->getRules());
        }

        return array_unique($rules);
    }

    /**
     * Convert to array for JSON response.
     */
    public function toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null): array
    {
        // Reset for fresh resolution
        $this->resolvedField = null;

        $data = parent::toArray($request, $model, $resource);

        // Add dynamic field specific data
        $data['props']['typeField'] = $this->typeField;
        $data['props']['isDynamic'] = true;

        // Include all type configurations for frontend
        if ($this->includeAllTypes) {
            $data['props']['types'] = $this->serializeTypes($request, $model, $resource);
        }

        // Include the currently matched type
        $field = $this->resolveFieldForModel($request, $model);
        if ($field !== null) {
            $data['props']['matchedType'] = $this->getTypeValue($request, $model);
            $data['props']['matchedField'] = $field->toArray($request, $model, $resource);
        }

        // Add default field config
        if ($this->defaultField !== null) {
            $data['props']['defaultField'] = $this->defaultField->toArray($request, $model, $resource);
        }

        return $data;
    }

    /**
     * Serialize all type mappings for frontend.
     */
    protected function serializeTypes(NadotaRequest $request, ?Model $model, ?ResourceInterface $resource): array
    {
        $types = [];

        foreach ($this->typeMap as $typeValue => $fieldOrCallback) {
            $field = $fieldOrCallback instanceof Closure
                ? $fieldOrCallback($model, $request)
                : $fieldOrCallback;

            if ($field instanceof Field) {
                $types[$typeValue] = $field->toArray($request, $model, $resource);
            }
        }

        return $types;
    }

    /**
     * Get props for frontend component.
     */
    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        $props = parent::getProps($request, $model, $resource);

        $props['typeField'] = $this->typeField;
        $props['isDynamic'] = true;

        return $props;
    }

    /**
     * Before save hook - delegate to resolved field.
     */
    public function beforeSave(Request $request, Model $model, string $operation): void
    {
        $field = $this->resolveFieldForModel($request, $model);

        if ($field !== null) {
            $field->beforeSave($request, $model, $operation);
        }
    }

    /**
     * After save hook - delegate to resolved field.
     */
    public function afterSave(Request $request, Model $model): void
    {
        $field = $this->resolveFieldForModel($request, $model);

        if ($field !== null) {
            $field->afterSave($request, $model);
        }
    }

    /**
     * Check if resolved field supports afterSave.
     */
    public function supportsAfterSave(): bool
    {
        if ($this->resolvedField !== null) {
            return $this->resolvedField->supportsAfterSave();
        }

        // Check if any type supports afterSave
        foreach ($this->typeMap as $fieldOrCallback) {
            if ($fieldOrCallback instanceof Field && $fieldOrCallback->supportsAfterSave()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get columns needed for SELECT - include type field.
     */
    public function getColumnsForSelect(string $modelClass): array
    {
        $columns = parent::getColumnsForSelect($modelClass);

        // Include the type field in SELECT
        if ($this->typeField !== null && !str_contains($this->typeField, '.')) {
            $columns[] = $this->typeField;
        }

        // Include columns from all possible nested fields
        foreach ($this->typeMap as $fieldOrCallback) {
            if ($fieldOrCallback instanceof Field) {
                $columns = array_merge($columns, $fieldOrCallback->getColumnsForSelect($modelClass));
            }
        }

        if ($this->defaultField instanceof Field) {
            $columns = array_merge($columns, $this->defaultField->getColumnsForSelect($modelClass));
        }

        return array_unique($columns);
    }

    /**
     * Get all nested relation fields from all type mappings.
     * This allows the eager loading system to load all possible relations
     * that might be needed for any record in the index.
     *
     * @return array<Field>
     */
    public function getNestedRelationFields(): array
    {
        $relationFields = [];

        foreach ($this->typeMap as $fieldOrCallback) {
            // Only handle non-closure fields for eager loading
            // Closures would need the model to resolve
            if ($fieldOrCallback instanceof Field && $fieldOrCallback->isRelationship()) {
                $relationFields[] = $fieldOrCallback;
            }
        }

        if ($this->defaultField instanceof Field && $this->defaultField->isRelationship()) {
            $relationFields[] = $this->defaultField;
        }

        return $relationFields;
    }

    /**
     * Get the relation name from the typeField if it uses dot notation.
     * E.g., 'formItem.form_item_type_id' returns 'formItem'
     *
     * @return string|null
     */
    public function getTypeFieldRelation(): ?string
    {
        if ($this->typeField === null || !str_contains($this->typeField, '.')) {
            return null;
        }

        return explode('.', $this->typeField)[0];
    }

    /**
     * Get relations that need to be eager loaded for this field.
     * Includes the typeField relation if it uses dot notation.
     *
     * @return array
     */
    public function getRequiredRelations(): array
    {
        $relations = [];

        $typeFieldRelation = $this->getTypeFieldRelation();
        if ($typeFieldRelation !== null) {
            $relations[] = $typeFieldRelation;
        }

        return $relations;
    }

    /**
     * Check if any nested field is a relationship.
     * This helps the eager loading system identify DynamicFields that contain relations.
     *
     * @return bool
     */
    public function hasNestedRelations(): bool
    {
        return !empty($this->getNestedRelationFields());
    }
}
