<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use Laravel\Mcp\Server;
use SchoolAid\Nadota\Mcp\Tools\DescribeAdminResourceTool;
use SchoolAid\Nadota\Mcp\Tools\GetAdminRecordTool;
use SchoolAid\Nadota\Mcp\Tools\ListAdminResourcesTool;
use SchoolAid\Nadota\Mcp\Tools\QueryAdminResourceTool;

class McpTestServer extends Server
{
    protected string $name = 'Nadota MCP test server';

    protected array $tools = [
        ListAdminResourcesTool::class,
        DescribeAdminResourceTool::class,
        QueryAdminResourceTool::class,
        GetAdminRecordTool::class,
    ];
}
