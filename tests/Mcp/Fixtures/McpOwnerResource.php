<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;

class McpOwnerResource extends Resource
{
    public string $model = McpOwner::class;

    protected array $searchableAttributes = ['name', 'id'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->sortable(),
        ];
    }
}
