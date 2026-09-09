<?php

namespace SchoolAid\Nadota\Tests\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\TestModel;

class ScopedOptionsResource extends Resource
{
    public string $model = TestModel::class;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),

            BelongsTo::make('Item', 'item', RelatedModelResource::class)
                ->scopedBy('owner', 'test_model_id'),
        ];
    }
}
