<?php

namespace SchoolAid\Nadota\Tests\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\DateTime;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

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