<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Its table is never created: loading it fails, so a test proves the MCP detail does not.
 */
class McpLog extends Model
{
    protected $table = 'mcp_logs_never_created';

    protected $guarded = [];
}
