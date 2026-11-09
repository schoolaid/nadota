<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Export;

/** Allows viewAny, refuses the `export` ability (AUD-013). */
class DenyExportPolicy
{
    public function viewAny($user): bool
    {
        return true;
    }

    public function export($user): bool
    {
        return false;
    }
}
