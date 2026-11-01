<?php

namespace SchoolAid\Nadota\Tests\Policies;

/**
 * A policy whose own `attach`/`detach` methods allow while `update` denies:
 * the explicit methods must win over the `update` fallback.
 */
class AttachMethodTestModelPolicy
{
    public function update($user, $model): bool
    {
        return false;
    }

    public function attach($user, $model, array $context = []): bool
    {
        return true;
    }

    public function detach($user, $model, array $context = []): bool
    {
        return true;
    }
}
