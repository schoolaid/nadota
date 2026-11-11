<?php

namespace SchoolAid\Nadota\Tests\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\SoftChild;

class SoftChildResource extends Resource
{
    public string $model = SoftChild::class;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Title', 'title'),
            BelongsTo::make('Parent', 'softParent', SoftParentResource::class),
        ];
    }
}
