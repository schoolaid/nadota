<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;

class McpLogResource extends Resource
{
    public string $model = McpLog::class;

    public function fields(NadotaRequest $request): array
    {
        return [Input::make('Message', 'message')];
    }
}
