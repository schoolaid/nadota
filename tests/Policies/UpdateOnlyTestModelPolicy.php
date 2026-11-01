<?php

namespace SchoolAid\Nadota\Tests\Policies;

/**
 * A policy that defines `update` but no `attach`/`detach`, like most host-app
 * policies. Attach/detach/sync/attachable must fall back to `update`.
 */
class UpdateOnlyTestModelPolicy
{
    public static bool $canUpdate = true;

    public function update($user, $model): bool
    {
        return static::$canUpdate;
    }
}
