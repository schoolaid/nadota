<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Policies;

/**
 * A policy that allows every ability except the ones listed in
 * config('nadota-tests.denied_abilities').
 *
 * Config is rebuilt with the application for every test, so denying an
 * ability never leaks into another test:
 *
 *     $this->useRealAuthorization();
 *     Gate::policy(Student::class, ConfigurableAbilityPolicy::class);
 *     config(['nadota-tests.denied_abilities' => ['delete']]);
 *
 * Every ability Nadota checks is declared as a real method because
 * ResourceAuthorizationService only consults the policy when
 * method_exists($policy, $ability).
 */
class ConfigurableAbilityPolicy
{
    public function viewAny($user): bool
    {
        return $this->allows('viewAny');
    }

    public function view($user, $model): bool
    {
        return $this->allows('view');
    }

    public function create($user): bool
    {
        return $this->allows('create');
    }

    public function update($user, $model): bool
    {
        return $this->allows('update');
    }

    public function delete($user, $model): bool
    {
        return $this->allows('delete');
    }

    public function restore($user, $model): bool
    {
        return $this->allows('restore');
    }

    public function forceDelete($user, $model): bool
    {
        return $this->allows('forceDelete');
    }

    public function attach($user, $model, array $context = []): bool
    {
        return $this->allows('attach');
    }

    public function detach($user, $model, array $context = []): bool
    {
        return $this->allows('detach');
    }

    protected function allows(string $ability): bool
    {
        return ! in_array($ability, config('nadota-tests.denied_abilities', []), true);
    }
}
