<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

it('is not a relationship on a plain field', function () {
    $field = Input::make('Name', 'name');

    expect($field->isRelationship())->toBeFalse()
        ->and($field->getRelation())->toBeNull()
        ->and($field->getModel())->toBeNull()
        ->and($field->getResource())->toBeNull()
        ->and($field->getAttributeForDisplay())->toBeNull();
});

it('becomes a relationship once a related model is set', function () {
    $field = Input::make('Grade', 'grade_id')->model(Grade::class);

    expect($field->isRelationship())->toBeTrue()
        ->and($field->getModel())->toBe(Grade::class);
});

it('records the relation name and related resource', function () {
    $field = BelongsTo::make('Grade', 'grade', GradeResource::class);

    expect($field->getRelation())->toBe('grade')
        ->and($field->getResource())->toBe(GradeResource::class)
        ->and($field->isRelationship())->toBeTrue();

    $field->relation('currentGrade')->resource(StudentResource::class);

    expect($field->getRelation())->toBe('currentGrade')
        ->and($field->getResource())->toBe(StudentResource::class);
});

describe('display of the related model', function () {
    it('reads the display attribute', function () {
        $field = BelongsTo::make('Grade', 'grade')->displayAttribute('name');

        expect($field->getAttributeForDisplay())->toBe('name')
            ->and($field->resolveDisplay(new Grade(['name' => 'First'])))->toBe('First');
    });

    it('prefers the display callback over the display attribute', function () {
        $field = BelongsTo::make('Grade', 'grade')
            ->displayAttribute('name')
            ->displayUsing(fn ($grade) => "{$grade->name} (L{$grade->level})");

        expect($field->hasDisplayCallback())->toBeTrue()
            ->and($field->resolveDisplay(new Grade(['name' => 'First', 'level' => 1])))->toBe('First (L1)');
    });

    it('returns null when neither is configured', function () {
        expect(BelongsTo::make('Grade', 'grade')->resolveDisplay(new Grade(['name' => 'First'])))->toBeNull();
    });

    it('does not turn a relation field into a computed field', function () {
        $relation = BelongsTo::make('Grade', 'grade')->displayUsing(fn ($grade) => $grade->name);
        $plain = Input::make('Shout', 'shout')->displayUsing(fn ($model) => 'x');

        expect($relation->isComputed())->toBeFalse()
            ->and($plain->isComputed())->toBeTrue();
    });
});

describe('query flags', function () {
    it('applies plain fields in both the index and the show query', function () {
        $field = Input::make('Name', 'name');

        expect($field->isAppliedInIndexQuery())->toBeTrue()
            ->and($field->isAppliedInShowQuery())->toBeTrue();
    });

    it('keeps to-many relations out of the index query', function () {
        $field = HasMany::make('Students', 'students', StudentResource::class);

        expect($field->isAppliedInIndexQuery())->toBeFalse()
            ->and($field->isAppliedInShowQuery())->toBeTrue();
    });

    it('can put a to-many relation back into the index query', function () {
        $field = HasMany::make('Students', 'students', StudentResource::class)->applyInIndexQuery();

        expect($field->isAppliedInIndexQuery())->toBeTrue();
    });
});

describe('options configuration', function () {
    it('builds the options URL from the parent resource key and the field key', function () {
        $url = Input::make('Grade', 'grade_id')->model(Grade::class)->withKey('grade')
            ->toArray(createNadotaRequest(), null, new StudentResource())['optionsUrl'];

        expect($url)->toBe('/nadota-api/student/resource/field/grade/options');
    });

    it('follows the configured API prefix', function () {
        config()->set('nadota.api.prefix', 'admin-api');

        $url = Input::make('Grade', 'grade_id')->model(Grade::class)
            ->toArray(createNadotaRequest(), null, new StudentResource())['optionsUrl'];

        expect($url)->toBe('/admin-api/student/resource/field/grade_id/options');
    });

    it('has no options URL without a parent resource', function () {
        expect(Input::make('Grade', 'grade_id')->model(Grade::class)->toArray(createNadotaRequest())['optionsUrl'])->toBeNull();
    });

    it('stores an options scope callback', function () {
        $scope = fn ($query) => $query->where('level', '>', 1);
        $field = BelongsTo::make('Grade', 'grade');

        expect($field->hasOptionsScope())->toBeFalse()
            ->and($field->getOptionsScope())->toBeNull();

        $field->optionsScope($scope);

        expect($field->hasOptionsScope())->toBeTrue()
            ->and($field->getOptionsScope())->toBe($scope);
    });

    it('tells an explicit "no limit" apart from an unset limit', function () {
        $field = BelongsTo::make('Grade', 'grade');

        expect($field->hasOptionsLimit())->toBeFalse()
            ->and($field->getOptionsLimit())->toBeNull();

        $field->optionsLimit(null);

        expect($field->hasOptionsLimit())->toBeTrue()
            ->and($field->getOptionsLimit())->toBeNull();

        expect($field->optionsLimit(25)->getOptionsLimit())->toBe(25);
    });
});
