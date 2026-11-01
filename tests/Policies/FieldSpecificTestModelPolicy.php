<?php

namespace SchoolAid\Nadota\Tests\Policies;

/**
 * A policy whose field-specific methods for `simpleTags` deny while the generic
 * `attach`/`detach` and `update` allow: the field-specific methods must win.
 */
class FieldSpecificTestModelPolicy
{
    public function update($user, $model): bool
    {
        return true;
    }

    public function attach($user, $model, array $context = []): bool
    {
        return true;
    }

    public function detach($user, $model, array $context = []): bool
    {
        return true;
    }

    public function attachSimpleTags($user, $model, array $context = []): bool
    {
        return false;
    }

    public function detachSimpleTags($user, $model, array $context = []): bool
    {
        return false;
    }
}
