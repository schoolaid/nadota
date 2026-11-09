<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphedByMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Fields\Traits\ManagesAttachments;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

dataset('to-many relation fields', [
    'BelongsToMany' => [fn () => BelongsToMany::make('Courses', 'courses', CourseResource::class)],
    'HasMany' => [fn () => HasMany::make('Students', 'students', StudentResource::class)],
    'MorphMany' => [fn () => MorphMany::make('Notes', 'notes', NoteResource::class)],
    'MorphToMany' => [fn () => MorphToMany::make('Labels', 'labels')],
    'MorphedByMany' => [fn () => MorphedByMany::make('Students', 'students', StudentResource::class)],
]);

it('is used by every to-many relation field, attachable by default', function ($field) {
    expect(class_uses($field))->toHaveKey(ManagesAttachments::class)
        ->and($field->isAttachable())->toBeTrue();
})->with('to-many relation fields');

it('starts with permissive defaults', function () {
    $field = HasMany::make('Students', 'students', StudentResource::class);

    expect($field->getAttachmentConfig())->toBe([
        'enabled' => true,
        'searchFields' => ['id'],
        'limit' => null,
        'showCountOnIndex' => false,
        'buttonLabel' => null,
        'displayFields' => [],
    ])
        ->and($field->getAttachableQueryCallback())->toBeNull();
});

it('reports every setting in the frontend config', function () {
    $callback = fn ($query) => $query->where('is_active', true);

    $field = HasMany::make('Students', 'students', StudentResource::class)
        ->attachableSearchFields(['name', 'email'])
        ->attachableQuery($callback)
        ->attachableLimit(5)
        ->showCountOnIndex()
        ->attachButtonLabel('Enroll')
        ->attachableDisplayFields(['name']);

    expect($field->getAttachmentConfig())->toBe([
        'enabled' => true,
        'searchFields' => ['name', 'email'],
        'limit' => 5,
        'showCountOnIndex' => true,
        'buttonLabel' => 'Enroll',
        'displayFields' => ['name'],
    ])
        ->and($field->getAttachableSearchFields())->toBe(['name', 'email'])
        ->and($field->getAttachableLimit())->toBe(5)
        ->and($field->shouldShowCountOnIndex())->toBeTrue()
        ->and($field->getAttachButtonLabel())->toBe('Enroll')
        ->and($field->getAttachableDisplayFields())->toBe(['name'])
        ->and($field->getAttachableQueryCallback())->toBe($callback);
});

it('can lift the limit again and be switched off', function () {
    $field = HasMany::make('Students', 'students', StudentResource::class)
        ->attachableLimit(5)
        ->attachableLimit(null)
        ->attachable(false);

    expect($field->getAttachableLimit())->toBeNull()
        ->and($field->isAttachable())->toBeFalse()
        ->and($field->getAttachmentConfig()['enabled'])->toBeFalse();
});

it('exposes the config under props.attachment only while attachable', function ($field) {
    $request = createNadotaRequest();

    expect($field->toArray($request)['props'])->toHaveKey('attachment')
        ->and($field->attachable(false)->toArray($request)['props'])->not->toHaveKey('attachment');
})->with([
    'BelongsToMany' => [fn () => BelongsToMany::make('Courses', 'courses', CourseResource::class)],
    'HasMany' => [fn () => HasMany::make('Students', 'students', StudentResource::class)],
    'MorphMany' => [fn () => MorphMany::make('Notes', 'notes', NoteResource::class)],
]);

it('drives the per-field attach/detach permissions of the resource', function () {
    FixtureSchema::create();
    $student = Student::create(['name' => 'Ana']);

    $resource = AdHocResource::withFields([
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->attachable(false),
        MorphMany::make('Notes', 'notes', NoteResource::class),
    ]);

    $permissions = $resource->getPermissionsForResource(createNadotaRequest(), $student);

    expect($permissions['fields'])->toBe([
        'notes' => ['attach' => true, 'detach' => true],
    ]);
});
