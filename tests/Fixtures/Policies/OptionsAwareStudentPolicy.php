<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Policies;

/**
 * Policy for the secondary-route HTTP tests (options, attachments, actions,
 * export, action events). Allows every ability except those listed in
 * config('nadota-tests.ops_denied'); config is rebuilt per test, so nothing
 * leaks between tests.
 *
 * Deliberately declares no viewOptions() and no export(): Nadota decides
 * those itself when the policy is silent. Use
 * OptionsAwareStudentPolicyWithOptions to have the policy decide them.
 */
class OptionsAwareStudentPolicy
{
    public function viewAny($user): bool
    {
        return $this->allows('viewAny');
    }

    public function view($user, $model): bool
    {
        return $this->allows('view');
    }

    public function update($user, $model): bool
    {
        return $this->allows('update');
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
        return ! in_array($ability, config('nadota-tests.ops_denied', []), true);
    }
}
