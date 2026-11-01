<?php

namespace SchoolAid\Nadota\Tests\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

class AttachableTestResource extends TestResource
{
    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),

            BelongsToMany::make('Tags', 'simpleTags')
                ->attachable(),
        ];
    }
}
