<?php

namespace SchoolAid\Nadota\Tests\Policies;

/**
 * A policy whose `before()` hook denies everything even though `update` allows:
 * the `update` fallback must go through the Gate so the hook runs.
 */
class BeforeHookTestModelPolicy
{
    public function before($user, string $ability): ?bool
    {
        return false;
    }

    public function update($user, $model): bool
    {
        return true;
    }
}
