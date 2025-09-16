<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SchoolAid\Nadota\Contracts\ResourceInterface;

trait FieldResolveTrait
{
    /**
     * Resolve the field value for display.
     *
     * @param Request $request
     * @param Model $model
     * @param ResourceInterface|null $resource
     * @return mixed
     */
    public function resolve(Request $request, Model $model, ?ResourceInterface $resource): mixed
    {
        $value = $model->{$this->getAttribute()};
        
        if ($value !== null) {
            return $value;
        }
        
        if ($this->hasDefault()) {
            return $this->resolveDefault($request, $model, $resource);
        }

        return $value;
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
        if ($value == null) {
            if ($this->hasDefault()) {
                return $this->resolveDefault($request, $model, $resource);
            }
        }

        return $value;
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
        if ($value == null) {
            if ($this->hasDefault()) {
                return $this->resolveDefault($request, $model, $resource);
            }
        }

        return $value;
    }
}