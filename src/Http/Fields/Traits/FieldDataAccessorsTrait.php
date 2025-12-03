<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

trait FieldDataAccessorsTrait
{
    public function label(string $label): static
    {
        $this->fieldData->label = $label;
        return $this;
    }

    public function type(string $type): static
    {
        $this->fieldData->type = $type;
        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->fieldData->placeholder = $placeholder;
        return $this;
    }

    public function component(string $component): static
    {
        $this->fieldData->component = $component;
        return $this;
    }

    public function getAttribute(): string
    {
        return $this->fieldData->attribute;
    }

    public function getType(): string
    {
        return $this->fieldData->type;
    }

    public function getLabel(): string
    {
        return $this->fieldData->label;
    }

    /**
     * Alias for getLabel() - for backwards compatibility
     */
    public function getName(): string
    {
        return $this->fieldData->label;
    }

    public function getPlaceholder(): string
    {
        return $this->fieldData->placeholder;
    }

    public function getComponent(): string
    {
        return $this->fieldData->component;
    }

    public function getKey(): string
    {
        return $this->fieldData->key;
    }

    /**
     * Properties for field state
     */
    protected bool $isReadonly = false;
    protected bool $isDisabled = false;

    /**
     * Set the field as readonly
     */
    public function readonly(bool $readonly = true): static
    {
        $this->isReadonly = $readonly;
        return $this;
    }

    /**
     * Check if field is readonly
     */
    public function isReadonly(): bool
    {
        return $this->isReadonly;
    }

    /**
     * Set the field as disabled
     */
    public function disabled(bool $disabled = true): static
    {
        $this->isDisabled = $disabled;
        return $this;
    }

    /**
     * Check if field is disabled
     */
    public function isDisabled(): bool
    {
        return $this->isDisabled;
    }

    /**
     * Add help text to the field
     */
    protected ?string $helpText = null;

    public function help(string $text): static
    {
        $this->helpText = $text;
        return $this;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    /**
     * Magic getter for accessing protected properties in tests.
     */
    public function __get($name)
    {
        if ($name === 'fieldData') {
            return $this->fieldData;
        }
        
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        
        throw new \InvalidArgumentException("Property {$name} does not exist on " . static::class);
    }
}