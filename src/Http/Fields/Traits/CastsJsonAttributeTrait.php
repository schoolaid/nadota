<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Whether the model casts the field's attribute to JSON (the cast encodes it once), shared by the
 * fields that take JSON text from the form (Json, Code).
 */
trait CastsJsonAttributeTrait
{
    protected function modelCastsAttributeToJson(Model $model): bool
    {
        $cast = $model->getCasts()[$this->getAttribute()] ?? null;

        if (!is_string($cast)) {
            return false;
        }

        // `encrypted:array` is one cast; `AsCollection:Foo` is the class plus an argument.
        $base = explode(':', $cast, 2)[0];
        $base = $base === 'encrypted' ? $cast : $base;

        return in_array(strtolower($base), ['array', 'json', 'object', 'collection', 'encrypted:array', 'encrypted:json', 'encrypted:object', 'encrypted:collection'], true)
            || is_a($base, \Illuminate\Database\Eloquent\Casts\AsArrayObject::class, true)
            || is_a($base, \Illuminate\Database\Eloquent\Casts\AsCollection::class, true)
            || is_a($base, \Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject::class, true)
            || is_a($base, \Illuminate\Database\Eloquent\Casts\AsEncryptedCollection::class, true);
    }
}
