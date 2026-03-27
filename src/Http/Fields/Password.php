<?php

namespace SchoolAid\Nadota\Http\Fields;

use Illuminate\Support\Facades\Hash;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Contracts\ResourceInterface;

class Password extends Field
{
    protected bool $confirmable = false;
    protected ?int $minLength = null;
    protected bool $showStrengthIndicator = false;

    /**
     * Custom hashing callback. Defaults to Hash::make.
     */
    protected $hashUsing = null;

    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::PASSWORD->value, static::safeConfig('nadota.fields.password.component', 'FieldPassword'));

        $this->onlyOnForms();

        // Password is required on creation, nullable on update by default
        $this->creationRules(['required']);
        $this->updateRules(['nullable']);
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

    /**
     * Set a custom hashing callback.
     *
     * @param callable $callback Receives (string $value) and returns the hashed string
     * @return static
     */
    public function hashUsing(callable $callback): static
    {
        $this->hashUsing = $callback;
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

    /**
     * Resolve the field value for storing.
     *
     * @param Request $request
     * @param Model $model
     * @param ResourceInterface|null $resource
     * @param mixed $value
     * @return mixed
     */
    public function resolveForStore(Request $request, Model $model, ?ResourceInterface $resource, $value): mixed
    {
        return $this->hashValue($value);
    }

    /**
     * Resolve the field value for updating.
     *
     * @param Request $request
     * @param Model $model
     * @param ResourceInterface|null $resource
     * @param mixed $value
     * @return mixed
     */
    public function resolveForUpdate(Request $request, Model $model, ?ResourceInterface $resource, $value): mixed
    {
        return $this->hashValue($value);
    }

    /**
     * Hash the value using the custom callback or Hash::make.
     */
    protected function hashValue(string $value): string
    {
        if ($this->hashUsing !== null) {
            return call_user_func($this->hashUsing, $value);
        }

        return Hash::make($value);
    }
}