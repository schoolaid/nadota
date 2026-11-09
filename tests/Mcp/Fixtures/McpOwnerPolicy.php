<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

class McpOwnerPolicy
{
    public function viewAny(McpUser $user): bool
    {
        return $user->grants('owner');
    }

    public function view(McpUser $user, McpOwner $owner): bool
    {
        return $user->grants('owner');
    }
}
