<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

class McpHalfPolicy
{
    public function viewAny(McpUser $user): bool
    {
        return true;
    }
}
