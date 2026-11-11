<?php

namespace SchoolAid\Nadota\Tests\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\SoftParent;

class SoftParentResource extends Resource
{
    public string $model = SoftParent::class;

    public function fields(NadotaRequest $request): array
    {
        return [Input::make('Name', 'name')];
    }
}
