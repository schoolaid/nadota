<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Contracts\ResourceInterface;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class CustomComponent extends Field
{
    protected bool $isRelationship = false;
    protected bool $applyInIndexQuery = false;
    protected bool $applyInShowQuery = false;
    /**
     * The path to the custom component
     * Note: Overrides Field::$componentPath with same nullable type for compatibility
     */
    protected ?string $componentPath = null;

    /**
     * Additional props to pass to the component
     */
    protected array $componentProps = [];

    /**
     * Position of the component: 'inside' (within detail card) or 'below' (after detail card)
     */
    protected string $position = 'inside';

    /**
     * withData() result of the serialization in progress, as [model, data]. The same data goes out
     * as `value`, `props.data` and `props.componentData`; it is computed once per toArray().
     */
    private ?array $serializedData = null;

    private bool $serializing = false;

    public function __construct(string $name, string $componentPath)
    {
        // Use a dummy attribute since this field doesn't map to a database column
        parent::__construct($name, 'custom_component_' . str_replace(' ', '_', strtolower($name)), FieldType::CUSTOM_COMPONENT->value, $componentPath);

        $this->componentPath = $componentPath;

        // Custom components are only shown on detail view by default
        $this->onlyOnDetail();
    }

    /**
     * Set the component path
     *
     * @param string $path
     * @return static
     */
    public function component(string $path): static
    {
        $this->componentPath = $path;

        // Keep the top-level `component` in sync with `props.componentPath`.
        return parent::component($path);
    }

    /**
     * Set additional props for the component
     *
     * @param array $props
     * @return static
     */
    public function withProps(array $props): static
    {
        $this->componentProps = array_merge($this->componentProps, $props);
        return $this;
    }

    /**
     * Add a single prop to the component
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function withProp(string $key, mixed $value): static
    {
        $this->componentProps[$key] = $value;
        return $this;
    }
    /**
     * Show the component only on forms
     *
     * @return static
     */
    public function onlyOnForms(): static
    {
        parent::onlyOnForms();
        return $this;
    }

    /**
     * Render the component inside the detail card (default)
     *
     * @return static
     */
    public function inside(): static
    {
        $this->position = 'inside';
        return $this;
    }

    /**
     * Render the component below the detail card
     *
     * @return static
     */
    public function below(): static
    {
        $this->position = 'below';
        return $this;
    }

    /**
     * Set custom position
     *
     * @param string $position 'inside' or 'below'
     * @return static
     */
    public function position(string $position): static
    {
        $this->position = $position;
        return $this;
    }

    protected function getProps(\Illuminate\Http\Request $request, ?\Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): array
    {
        $props = parent::getProps($request, $model, $resource);

        // Add the component path
        $props['componentPath'] = $this->componentPath;

        // Add position for rendering
        $props['position'] = $this->position;

        // Add any additional component props
        $props['componentProps'] = $this->componentProps;

        // The data callback's result (computed once per serialization, see evaluateDataCallback())
        if ($this->dataCallback !== null && $model !== null) {
            $props['componentData'] = $this->evaluateDataCallback($model, $resource);
        }

        return $props;
    }

    /**
     * Override resolve to return component data
     */
    public function resolve(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model, ?\SchoolAid\Nadota\Contracts\ResourceInterface $resource): mixed
    {
        // Return null or component data if the callback is defined
        if ($this->dataCallback !== null) {
            return $this->evaluateDataCallback($model, $resource);
        }

        return null;
    }

    public function toArray(NadotaRequest $request, ?Model $model = null, ?ResourceInterface $resource = null): array
    {
        $this->serializing = true;

        try {
            return parent::toArray($request, $model, $resource);
        } finally {
            $this->serializing = false;
            $this->serializedData = null;
        }
    }

    /**
     * Within one toArray() the callback runs once for `props.data`, `props.componentData` and
     * `value`; outside of it (export, a direct resolve()) every call runs it again.
     */
    protected function evaluateDataCallback(Model $model, ?ResourceInterface $resource): mixed
    {
        if ($this->serializedData !== null && $this->serializedData[0] === $model) {
            return $this->serializedData[1];
        }

        $data = parent::evaluateDataCallback($model, $resource);

        if ($this->serializing) {
            $this->serializedData = [$model, $data];
        }

        return $data;
    }

    public function shouldSkipFill(): bool
    {
        return true;
    }

    public function isComputed(): bool
    {
        return false;
    }

    /**
     * Override fill to prevent any data storage
     */
    public function fill(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model): void
    {
        return;
    }

    /**
     * Override to return empty array - custom components don't need database columns
     */
    public function getColumnsForSelect(string $modelClass): array
    {
        return [];
    }
}