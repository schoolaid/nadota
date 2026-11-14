<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model without a policy: Nadota would let anyone read it, the MCP tools let no one.
 */
class McpLoose extends Model
{
    protected $table = 'mcp_owners';

    protected $guarded = [];
}
