<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class CustomComponent extends Field
{
    protected bool $isRelationship = false;
    protected bool $applyInIndexQuery = false;
    protected bool $applyInShowQuery = false;
    /**
     * The path to the custom component
     */
    protected string $componentPath;

    /**
     * Additional props to pass to the component
     */
    protected array $componentProps = [];

    /**
     * Position of the component: 'inside' (within detail card) or 'below' (after detail card)
     */
    protected string $position = 'inside';

    public function __construct(string $name, string $componentPath)
    {
        // Use a dummy attribute since this field doesn't map to a database column
        parent::__construct($name, 'custom_component_' . str_replace(' ', '_', strtolower($name)), FieldType::CUSTOM_COMPONENT->value, $componentPath);

        $this->componentPath = $componentPath;

        // Custom components are only shown on detail view by default
        $this->onlyOnDetail();

        // Mark as computed since it doesn't store data
        $this->computed = true;
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
        return $this;
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

        // If there's a data callback, execute it and add the result
        if ($this->dataCallback !== null && $model !== null) {
            $props['componentData'] = call_user_func($this->dataCallback, $model, $resource);
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
            return call_user_func($this->dataCallback, $model, $resource);
        }

        return null;
    }

    /**
     * Override fill to prevent any data storage
     */
    public function fill(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $model): void
    {
        // Custom components don't fill any model attributes
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