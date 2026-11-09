<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model whose policy defines viewAny but not view.
 */
class McpHalf extends Model
{
    protected $table = 'mcp_owners';

    protected $guarded = [];
}
