<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Policies;

/** OptionsAwareStudentPolicy plus explicit viewOptions() and export() abilities. */
class OptionsAwareStudentPolicyWithOptions extends OptionsAwareStudentPolicy
{
    public function viewOptions($user): bool
    {
        return $this->allows('viewOptions');
    }

    public function export($user): bool
    {
        return $this->allows('export');
    }
}
