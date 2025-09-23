<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Contracts\FieldInterface;
use SchoolAid\Nadota\Http\Fields\DataTransferObjects\FieldDTO;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Fields\Traits\{DefaultValueTrait,
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
    use RelationshipTrait;
    use DefaultValueTrait;
    use FieldDataAccessorsTrait;
    use FieldResolveTrait;
    use FilterableTrait;
    use Makeable;
    use SearchableTrait;
    use SortableTrait;
    use ValidationTrait;
    use VisibilityTrait;

    protected FieldDTO $fieldData;

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

    public function __construct(string $name, string $attribute, string $type = FieldType::TEXT->value, string $component = null)
    {
        $this->fieldData = new FieldDTO(
            name: $name,
            label: $name,
            id: str_replace(' ', '_', $name),
            attribute: $attribute,
            placeholder: $name,
            type: $type,
            component: $component ?? config('nadota.fields.input.component')
        );
    }

    public function key(): string
    {
        return str_replace(' ', '', strtolower($this->fieldData->name));
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
            'showOnIndex' => $this->isShowOnIndex($request, $model),
            'showOnDetail' => $this->isShowOnDetail($request, $model),
            'showOnCreation' => $this->isShowOnCreation($request, $model),
            'showOnUpdate' => $this->isShowOnUpdate($request, $model),
            'props' => $this->getProps($request, $model, $resource),
            'rules' => $this->getRules(),
            'optionsUrl' => $this->getOptionsUrl($resource)
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
     * Fill the model attribute with the field's value
     * Computed fields are skipped as they don't store data
     *
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function fill(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model): void
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
}
