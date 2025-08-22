<?php

namespace Said\Nadota\Tests\Resources;

use Said\Nadota\Http\Fields\Input;
use Said\Nadota\Http\Fields\DateTime;
use Said\Nadota\Http\Fields\Toggle;
use Said\Nadota\Http\Requests\NadotaRequest;
use Said\Nadota\Resource;
use Said\Nadota\Tests\Models\TestModel;

class TestResource extends Resource
{
    public string $model = TestModel::class;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')
                ->sortable()
                ->searchable()
                ->required(),
                
            Input::make('Email', 'email')
                ->sortable()
                ->searchable()
                ->rules(['email']),
                
            Toggle::make('Active', 'is_active')
                ->sortable()
                ->filterable(),
                
            DateTime::make('Published At', 'published_at')
                ->sortable()
                ->filterable(),
        ];
    }
}