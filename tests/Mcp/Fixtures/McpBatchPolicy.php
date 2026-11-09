<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

class McpBatchPolicy
{
    public function viewAny(McpUser $user): bool
    {
        return $user->grants('batch');
    }

    /**
     * A sealed batch is listed but cannot be opened.
     */
    public function view(McpUser $user, McpBatch $batch): bool
    {
        return $user->grants('batch') && $batch->status !== 'sealed';
    }
}
