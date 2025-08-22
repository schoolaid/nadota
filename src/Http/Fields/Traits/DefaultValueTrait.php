<?php

namespace Said\Nadota\Http\Fields\Traits;

trait DefaultValueTrait
{
    protected $defaultValue;
    protected ?string $defaultFromAttribute = null;
    protected $defaultCondition = null;

    public function default($value): static
    {
        $this->defaultValue = $value;
        return $this;
    }

    public function defaultFromAttribute(string $attribute): static
    {
        $this->defaultFromAttribute = $attribute;
        return $this;
    }

    public function defaultUsing(callable $callback): static
    {
        $this->defaultValue = $callback;
        return $this;
    }

    public function defaultWhen(callable $condition, mixed $value): static
    {
        $this->defaultCondition = $condition;
        $this->defaultValue = $value;
        return $this;
    }

    public function hasDefault(): bool
    {
        return !is_null($this->defaultValue) || !is_null($this->defaultFromAttribute);
    }

    public function resolveDefault($request, $item = null, $resource = null)
    {
        // Check conditional default
        if ($this->defaultCondition && !call_user_func($this->defaultCondition, $request, $item, $resource)) {
            return null;
        }

        // Use attribute default
        if ($this->defaultFromAttribute && $item) {
            // Support nested attributes with dot notation
            $segments = explode('.', $this->defaultFromAttribute);
            $value = $item;
            
            foreach ($segments as $segment) {
                if (is_object($value)) {
                    $value = $value->{$segment} ?? null;
                } elseif (is_array($value)) {
                    $value = $value[$segment] ?? null;
                } else {
                    return null;
                }
            }
            
            return $value;
        }

        // Use callback or static default
        if (is_callable($this->defaultValue)) {
            return call_user_func($this->defaultValue, $request, $item, $resource);
        }
        
        return $this->defaultValue;
    }

    public function getDefault()
    {
        return $this->defaultValue;
    }

    public function getDefaultFromAttribute(): ?string
    {
        return $this->defaultFromAttribute;
    }
}
