<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;

class McpLooseResource extends Resource
{
    public string $model = McpLoose::class;

    public function fields(NadotaRequest $request): array
    {
        return [Input::make('Name', 'name')];
    }
}
