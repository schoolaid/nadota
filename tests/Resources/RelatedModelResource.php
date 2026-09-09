<?php

namespace SchoolAid\Nadota\Tests\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Models\RelatedModel;

class RelatedModelResource extends Resource
{
    public string $model = RelatedModel::class;

    /**
     * Options search reads this resource property, not the fields' ->searchable()
     * flags: SearchesOptions::applyResourceSearch() only consults
     * getSearchableAttributes(). Leaving it empty makes every search match.
     */
    protected array $searchableAttributes = ['title'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Title', 'title')->searchable(),
        ];
    }
}
