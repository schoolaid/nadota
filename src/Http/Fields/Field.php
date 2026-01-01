<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Contracts\FieldInterface;
use SchoolAid\Nadota\Http\Fields\DataTransferObjects\FieldDTO;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Traits\{
    DefaultValueTrait,
    DependsOnTrait,
    FieldDataAccessorsTrait,
    FieldResolveTrait,
    FilterableTrait,
    RelationshipTrait,
    SearchableTrait,
    SortableTrait,
    ValidationTrait,
    VisibilityTrait
};
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Traits\Makeable;

abstract class Field implements FieldInterface
{
    use DefaultValueTrait;
    use DependsOnTrait;
    use FieldDataAccessorsTrait;
    use FieldResolveTrait;
    use FilterableTrait;
    use Makeable;
    use RelationshipTrait;
    use SearchableTrait;
    use SortableTrait;
    use ValidationTrait;
    use VisibilityTrait;

    protected FieldDTO $fieldData;

    /**
     * Safely get a config value, returning default if container is not available.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected static function safeConfig(string $key, mixed $default = null): mixed
    {
        try {
            return config($key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Field width (e.g., 'full', '1/2', '1/3', '1/4', '2/3', '3/4', or custom CSS value)
     */
    protected ?string $width = null;

    /**
     * Tab size for fields that support it
     */
    protected int $tabSize = 4;

    /**
     * Maximum height for the field
     */
    protected ?int $maxHeight = null;

    /**
     * Minimum height for the field
     */
    protected ?int $minHeight = null;

    /**
     * Callback for computing display value
     */
    protected $displayCallback = null;

    /**
     * Whether this is a computed field (not stored in database)
     */
    protected bool $computed = false;

    /**
     * Whether this is a custom field (user-defined, not a Nadota built-in)
     */
    protected bool $isCustomField = false;

    /**
     * Path to the custom component (e.g., '@/components/fields/MyField.vue')
     */
    protected ?string $componentPath = null;

    public function __construct(string $label, string $attribute, string $type = FieldType::TEXT->value, ?string $component = null)
    {
        $this->fieldData = new FieldDTO(
            label: $label,
            attribute: $attribute,
            key: $attribute,
            placeholder: $label,
            type: $type,
            component: $component ?? static::safeConfig('nadota.fields.input.component', 'field')
        );
    }

    public function key(): string
    {
        return $this->fieldData->key;
    }

    public function toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null): array
    {
        $data = array_merge($this->fieldData->toArray(), [
            'key' => $this->key(),
            'readonly' => $this->isReadonly(),
            'disabled' => $this->isDisabled(),
            'required' => $this->isRequired(),
            'helpText' => $this->getHelpText(),
            'sortable' => $this->isSortable(),
            'searchable' => $this->isSearchable(),
            'filterable' => $this->isFilterable(),
            'filterableRange' => $this->isFilterableRange(),
            'filterKeys' => $this->getFilterKeys(),
            'showOnIndex' => $this->isShowOnIndex($request, $model),
            'showOnDetail' => $this->isShowOnDetail($request, $model),
            'showOnCreation' => $this->isShowOnCreation($request, $model),
            'showOnUpdate' => $this->isShowOnUpdate($request, $model),
            'props' => $this->getProps($request, $model, $resource),
            'rules' => $this->getRules(),
            'optionsUrl' => $this->getOptionsUrl($resource),
            'dependencies' => $this->getDependencyConfig(),
        ]);

        if ($model) {
            $data['value'] = $this->resolve($request, $model, $resource);
        }

        return $data;
    }

    /**
     * Data callback to compute dynamic data for the component
     */
    protected $dataCallback = null;

    /**
     * Set a callback to compute dynamic data for the component
     *
     * @param callable $callback
     * @return static
     */
    public function withData(callable $callback): static
    {
        $this->dataCallback = $callback;
        return $this;
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        $props = [];

        if ($this->width !== null) {
            $props['width'] = $this->width;
        }

        if ($this->tabSize !== 4) { // Only include if not default
            $props['tabSize'] = $this->tabSize;
        }

        if ($this->maxHeight !== null) {
            $props['maxHeight'] = $this->maxHeight;
        }

        if ($this->minHeight !== null) {
            $props['minHeight'] = $this->minHeight;
        }

        if ($this->dataCallback !== null && $model !== null) {
            $props['data'] = call_user_func($this->dataCallback, $model, $resource);
        }

        // Custom field properties
        if ($this->isCustomField) {
            $props['isCustomField'] = true;
        }

        if ($this->componentPath !== null) {
            $props['componentPath'] = $this->componentPath;
        }

        return $props;
    }

    public function getOptions(): array
    {
        return [];
    }

    /**
     * Set the field width
     *
     * @param string $width Width value (e.g., 'full', '1/2', '1/3', '1/4', '2/3', '3/4', or custom CSS value)
     * @return static
     */
    public function width(string $width): static
    {
        $this->width = $width;
        return $this;
    }

    /**
     * Set the tab size for fields that support it
     *
     * @param int $tabSize Tab size value
     * @return static
     */
    public function tabSize(int $tabSize): static
    {
        $this->tabSize = $tabSize;
        return $this;
    }

    /**
     * Set the maximum height for the field
     *
     * @param int|null $maxHeight Maximum height in pixels
     * @return static
     */
    public function maxHeight(?int $maxHeight): static
    {
        $this->maxHeight = $maxHeight;
        return $this;
    }

    /**
     * Set the minimum height for the field
     *
     * @param int|null $minHeight Minimum height in pixels
     * @return static
     */
    public function minHeight(?int $minHeight): static
    {
        $this->minHeight = $minHeight;
        return $this;
    }

    /**
     * Mark this field as a custom field.
     *
     * @param bool $isCustom
     * @return static
     */
    public function customField(bool $isCustom = true): static
    {
        $this->isCustomField = $isCustom;
        return $this;
    }

    /**
     * Set the path to the custom component.
     * This is useful for frontend frameworks that need to dynamically import components.
     *
     * @param string $path Path to the component (e.g., '@/components/fields/MyField.vue')
     * @return static
     */
    public function componentPath(string $path): static
    {
        $this->componentPath = $path;
        $this->isCustomField = true;
        return $this;
    }

    /**
     * Check if this is a custom field.
     *
     * @return bool
     */
    public function isCustomField(): bool
    {
        return $this->isCustomField;
    }

    /**
     * Get the component path.
     *
     * @return string|null
     */
    public function getComponentPath(): ?string
    {
        return $this->componentPath;
    }

    /**
     * Set the field to full width
     *
     * @return static
     */
    public function fullWidth(): static
    {
        return $this->width('full');
    }

    /**
     * Set the field to half width
     *
     * @return static
     */
    public function halfWidth(): static
    {
        return $this->width('1/2');
    }

    /**
     * Set the field to one third width
     *
     * @return static
     */
    public function oneThirdWidth(): static
    {
        return $this->width('1/3');
    }

    /**
     * Set the field to two thirds width
     *
     * @return static
     */
    public function twoThirdsWidth(): static
    {
        return $this->width('2/3');
    }

    /**
     * Set the field to one quarter width
     *
     * @return static
     */
    public function oneQuarterWidth(): static
    {
        return $this->width('1/4');
    }

    /**
     * Set the field to three quarters width
     *
     * @return static
     */
    public function threeQuartersWidth(): static
    {
        return $this->width('3/4');
    }

    /**
     * Define a callback to compute the display value
     * This makes the field computed (read-only, not stored in database)
     *
     * @param callable $callback Receives ($model, $resource) and returns the display value
     * @return static
     */
    public function displayUsing(callable $callback): static
    {
        $this->displayCallback = $callback;
        $this->computed = true;

        return $this;
    }

    /**
     * Mark this field as computed (not stored in database)
     *
     * @param bool $computed
     * @return static
     */
    public function computed(bool $computed = true): static
    {
        $this->computed = $computed;

        if ($computed) {
            // Computed fields are read-only and only shown on index and detail
            $this->readonly();
            $this->hideFromCreation();
            $this->hideFromUpdate();
        }

        return $this;
    }

    /**
     * Check if this is a computed field
     *
     * @return bool
     */
    public function isComputed(): bool
    {
        return $this->computed;
    }

    /**
     * Check if field has a display callback
     *
     * @return bool
     */
    public function hasDisplayCallback(): bool
    {
        return $this->displayCallback !== null;
    }

    /**
     * Get the columns this field needs for SELECT queries.
     * Override in relationship fields to return appropriate columns.
     *
     * @param string $modelClass The parent model class
     * @return array Array of column names
     */
    public function getColumnsForSelect(string $modelClass): array
    {
        // Regular fields just need their attribute
        $attribute = $this->getAttribute();

        return $attribute ? [$attribute] : [];
    }

    /**
     * Columns to exclude from the related model.
     */
    protected ?array $exceptColumns = null;

    /**
     * Field keys/relations to exclude from the related model.
     */
    protected ?array $exceptFieldKeys = null;

    /**
     * Exclude specific columns from the related model selection.
     * Requires a resource to be set.
     *
     * @param array $columns Column names to exclude
     * @return static
     */
    public function except(array $columns): static
    {
        $this->exceptColumns = $columns;
        return $this;
    }

    /**
     * Exclude specific fields from the related model by key or relation name.
     * This excludes both the field columns AND their eager loading.
     *
     * @param array $fieldKeys Field keys or relation names to exclude
     * @return static
     */
    public function exceptFields(array $fieldKeys): static
    {
        $this->exceptFieldKeys = $fieldKeys;
        return $this;
    }

    /**
     * Get the excluded field keys.
     */
    public function getExceptFieldKeys(): ?array
    {
        return $this->exceptFieldKeys;
    }

    /**
     * Get columns to select from the related model when eager loading.
     * Override in relationship fields to customize eager loading.
     *
     * @param Request $request
     * @return array|null Columns array, or null to select all
     */
    public function getRelatedColumns(Request $request): ?array
    {
        // If a field has a resource, use its columns
        if ($this->getResource()) {
            $resourceClass = $this->getResource();

            // Verify the resource class implements ResourceInterface (is a Nadota Resource)
            if (!is_subclass_of($resourceClass, ResourceInterface::class)) {
                return null;
            }

            $resource = new $resourceClass;

            // Get filtered fields (excluding exceptFieldKeys)
            $fields = $this->getFilteredResourceFields($resource, $request);

            // Get columns from filtered fields
            $columns = collect($fields)
                ->filter(fn($field) => $field->isAppliedInShowQuery())
                ->flatMap(fn($field) => $field->getColumnsForSelect($resource->model))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // Always include a primary key
            $columns = array_unique([...$columns, $resourceClass::$attributeKey]);

            // Apply except column filter if set
            if ($this->exceptColumns !== null) {
                $columns = array_values(array_diff($columns, $this->exceptColumns));
            }

            return $columns;
        }

        // Default: select all columns
        return null;
    }

    /**
     * Get resource fields filtered by exceptFieldKeys.
     * Uses flattenFields to extract fields from sections.
     */
    protected function getFilteredResourceFields($resource, Request $request): array
    {
        // Use flattenFields if available (for Resources with sections support)
        $fields = method_exists($resource, 'flattenFields')
            ? $resource->flattenFields($request)->toArray()
            : $resource->fields($request);

        if ($this->exceptFieldKeys === null) {
            return $fields;
        }

        return collect($fields)
            ->filter(function ($field) {
                $key = $field->key();
                $relation = method_exists($field, 'getRelation') ? $field->getRelation() : null;

                // Exclude if key or relation matches
                return !in_array($key, $this->exceptFieldKeys) &&
                       ($relation === null || !in_array($relation, $this->exceptFieldKeys));
            })
            ->values()
            ->toArray();
    }

    /**
     * Fill the model attribute with the field's value
     * Computed fields are skipped as they don't store data
     *
     * @param Request $request
     * @param Model $model
     * @return void
     */
    public function fill(Request $request, Model $model): void
    {
        // Don't fill computed fields - they are read-only
        if ($this->isComputed()) {
            return;
        }

        // Don't fill readonly or disabled fields
        if ($this->isReadonly() || $this->isDisabled()) {
            return;
        }

        $requestAttribute = $this->getAttribute();

        if ($request->has($requestAttribute)) {
            $model->{$this->getAttribute()} = $request->get($requestAttribute);
        }
    }

    /**
     * Perform operations before the model is saved.
     *
     * Override this method to add custom pre-save logic.
     *
     * @param Request $request The current request
     * @param Model $model The model being saved
     * @param string $operation The operation type: 'store' or 'update'
     * @return void
     */
    public function beforeSave(Request $request, Model $model, string $operation): void
    {
        // Override in child classes if needed
    }

    /**
     * Perform operations after the model is saved.
     *
     * Override this method to handle relationships or other
     * operations that require the model to have an ID.
     *
     * @param Request $request The current request
     * @param Model $model The saved model (with ID)
     * @return void
     */
    public function afterSave(Request $request, Model $model): void
    {
        // Override in child classes if needed
    }

    /**
     * Resolve the field value for export.
     *
     * Override this method in fields that need to transform values
     * for export (e.g., Select fields converting value to label).
     *
     * @param Request $request
     * @param Model $model
     * @param ResourceInterface|null $resource
     * @return mixed
     */
    public function resolveForExport(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        // Default: use standard resolve
        return $this->resolve($request, $model, $resource);
    }

    /**
     * Determine if this field supports the afterSave callback.
     *
     * Fields that manage relationships or need the model ID
     * should return true.
     *
     * @return bool
     */
    public function supportsAfterSave(): bool
    {
        return false;
    }
}
