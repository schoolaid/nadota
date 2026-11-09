<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Color;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\MorphedByMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Label;

/** Route key: "label". Covers MorphedByMany and Color. */
class LabelResource extends Resource
{
    public string $model = Label::class;

    protected array $searchableAttributes = ['name'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->required(),
            Color::make('Color', 'color'),
            MorphedByMany::make('Students', 'students', StudentResource::class),
        ];
    }
}
