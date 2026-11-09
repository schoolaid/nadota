<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;

/** Route key: "note". Covers MorphTo with two morph targets. */
class NoteResource extends Resource
{
    public string $model = Note::class;

    protected array $searchableAttributes = ['body'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Textarea::make('Body', 'body')->required(),
            Toggle::make('Pinned', 'pinned')->filterable(),
            MorphTo::make('Notable', 'notable', [
                'student' => StudentResource::class,
                'grade' => GradeResource::class,
            ]),
        ];
    }
}
