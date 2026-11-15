<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphedByMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Fields\Traits\AttachesOwnedChildren;
use SchoolAid\Nadota\Http\Fields\Traits\ManagesAttachments;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

dataset('pivot relation fields', [
    'BelongsToMany' => [fn () => BelongsToMany::make('Courses', 'courses', CourseResource::class)],
    'MorphToMany' => [fn () => MorphToMany::make('Labels', 'labels')],
    'MorphedByMany' => [fn () => MorphedByMany::make('Students', 'students', StudentResource::class)],
]);

dataset('owned children relation fields', [
    'HasMany' => [fn () => HasMany::make('Students', 'students', StudentResource::class)],
    'MorphMany' => [fn () => MorphMany::make('Notes', 'notes', NoteResource::class)],
]);

it('is used by every pivot relation field, attachable by default', function ($field) {
    expect(class_uses($field))->toHaveKey(ManagesAttachments::class)
        ->and($field->isAttachable())->toBeTrue();
})->with('pivot relation fields');

it('is used by HasMany and MorphMany, attachable only on opt-in', function ($field) {
    // Attaching rewrites the child's owner columns, so it is off until ->attachable()
    expect(class_uses($field))->toHaveKey(ManagesAttachments::class)
        ->toHaveKey(AttachesOwnedChildren::class)
        ->and($field->isAttachable())->toBeFalse()
        ->and($field->attachable()->isAttachable())->toBeTrue()
        ->and($field->isReassignable())->toBeFalse();
})->with('owned children relation fields');

it('turns attach on when a field is made reassignable', function ($field) {
    expect($field->reassignable()->isAttachable())->toBeTrue()
        ->and($field->isReassignable())->toBeTrue()
        ->and($field->attachable(false)->isReassignable())->toBeFalse();
})->with('owned children relation fields');

it('starts with permissive defaults', function () {
    $field = BelongsToMany::make('Courses', 'courses', CourseResource::class);

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
        ->attachable()
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
        ->attachable()
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
    'HasMany' => [fn () => HasMany::make('Students', 'students', StudentResource::class)->attachable()],
    'MorphMany' => [fn () => MorphMany::make('Notes', 'notes', NoteResource::class)->attachable()],
]);

it('adds the resolved abilities to the attachment config of HasMany and MorphMany', function ($field) {
    expect($field->attachable()->toArray(createNadotaRequest())['props']['attachment'])
        ->toMatchArray(['enabled' => true, 'reassignable' => false])
        ->toHaveKeys(['canAttach', 'canDetach']);
})->with('owned children relation fields');

it('drives the per-field attach/detach permissions of the resource', function () {
    FixtureSchema::create();
    $student = Student::create(['name' => 'Ana']);

    $resource = AdHocResource::withFields([
        Input::make('Name', 'name'),
        BelongsToMany::make('Courses', 'courses', CourseResource::class)->attachable(false),
        MorphMany::make('Notes', 'notes', NoteResource::class)->attachable(),
        HasMany::make('Profile notes', 'profileNotes', NoteResource::class), // not attachable: left out
    ]);

    $permissions = $resource->getPermissionsForResource(createNadotaRequest(), $student);

    expect($permissions['fields'])->toBe([
        'notes' => ['attach' => true, 'detach' => true],
    ]);
});
