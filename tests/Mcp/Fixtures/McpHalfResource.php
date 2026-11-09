<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;

class McpHalfResource extends Resource
{
    public string $model = McpHalf::class;

    public function fields(NadotaRequest $request): array
    {
        return [Input::make('Name', 'name')];
    }
}
