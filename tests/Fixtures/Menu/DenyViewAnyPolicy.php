<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Menu;

/**
 * Policy that refuses viewAny and allows everything else. Register it with
 * Gate::policy(Model::class, DenyViewAnyPolicy::class) after
 * $this->useRealAuthorization().
 */
class DenyViewAnyPolicy
{
    public function viewAny($user): bool
    {
        return false;
    }

    public function view($user, $model): bool
    {
        return true;
    }
}
