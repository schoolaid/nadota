<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;

/**
 * Route key: "filter-lab-note".
 *
 * NoteResource with a filterable MorphTo (type + entity filter pair) and a
 * filterable Toggle. Used by tests/Feature/Http/Filters.
 */
class FilterLabNoteResource extends Resource
{
    public string $model = Note::class;

    protected array $searchableAttributes = ['body'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Textarea::make('Body', 'body'),
            Toggle::make('Pinned', 'pinned')->filterable(),
            MorphTo::make('Notable', 'notable', [
                'student' => StudentResource::class,
                'grade' => GradeResource::class,
            ])->filterable(),
        ];
    }
}
