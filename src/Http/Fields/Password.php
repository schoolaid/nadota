<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Contracts\ResourceInterface;

class Password extends Field
{
    protected bool $confirmable = false;
    protected ?int $minLength = null;
    protected bool $showStrengthIndicator = false;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::PASSWORD->value, static::safeConfig('nadota.fields.password.component', 'FieldPassword'));
        
        $this->onlyOnForms();
    }

    public function confirmable(bool $confirmable = true): static
    {
        $this->confirmable = $confirmable;
        return $this;
    }

    public function minLength(int $length): static
    {
        $this->minLength = $length;
        return $this;
    }

    public function showStrengthIndicator(bool $show = true): static
    {
        $this->showStrengthIndicator = $show;
        return $this;
    }

    public function getRules(): array
    {
        $rules = parent::getRules();
        
        // Add minimum length validation if specified
        if ($this->minLength !== null) {
            $rules[] = 'min:' . $this->minLength;
        }
        
        // Add string validation
        $rules[] = 'string';
        
        // Add confirmed validation if confirmable
        if ($this->confirmable) {
            $rules[] = 'confirmed';
        }
        
        return $rules;
    }

    protected function getProps(Request $request, ?Model $model, ?ResourceInterface $resource): array
    {
        return array_merge(parent::getProps($request, $model, $resource), [
            'confirmable' => $this->confirmable,
            'minLength' => $this->minLength,
            'showStrengthIndicator' => $this->showStrengthIndicator,
        ]);
    }

    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        // Never return actual password values
        return null;
    }
}