<?php

namespace SchoolAid\Nadota\Tests\Resources;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * One-to-many fields for the attach/detach tests. The static flags let a test change how a field
 * is declared, since a field key cannot repeat in one resource:
 * - `relatedModels`: a plain HasMany (nullable FK), attachable unless `$attachable` is false;
 *   `$reassignable` lets it take children of other parents. `attachableQuery` hides «Hidden».
 * - `notes`: a morphMany declared as HasMany (like `attachments` in host apps).
 * - `morphNotes` / `comments`: MorphMany fields, nullable morph columns / NOT NULL ones.
 * - `plainRelated`: a HasMany with no attachable() call (the default).
 */
class HasManyAttachTestResource extends TestResource
{
    public static bool $attachable = true;

    public static bool $reassignable = false;

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name'),

            HasMany::make('Related', 'relatedModels')
                ->attachable(static::$attachable)
                ->reassignable(static::$reassignable)
                ->attachableQuery(fn ($query) => $query->where('title', '!=', 'Hidden')),

            HasMany::make('Notes', 'notes')->attachable(),

            MorphMany::make('Morph notes', 'morphNotes')->attachable(),

            MorphMany::make('Comments', 'comments')->attachable(),

            HasMany::make('Plain related', 'plainRelated'),
        ];
    }
}
