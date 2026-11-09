<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;

it('derives key and placeholder from the constructor arguments', function () {
    $field = Input::make('Full Name', 'full_name');

    expect($field->getLabel())->toBe('Full Name')
        ->and($field->getName())->toBe('Full Name')
        ->and($field->getAttribute())->toBe('full_name')
        ->and($field->getKey())->toBe('full_name')
        ->and($field->getPlaceholder())->toBe('Full Name');
});

it('serialises what the fluent setters write', function () {
    $field = Input::make('Name', 'name')
        ->label('Student name')
        ->placeholder('Type a name')
        ->type('custom-type')
        ->component('FieldFancy');

    $array = $field->toArray(createNadotaRequest());

    expect($array)
        ->toMatchArray([
            'label' => 'Student name',
            'placeholder' => 'Type a name',
            'type' => 'custom-type',
            'component' => 'FieldFancy',
            'attribute' => 'name',
            'key' => 'name',
        ])
        ->and($field->getName())->toBe('Student name');
});

it('keeps the original placeholder when only the label changes', function () {
    $field = Input::make('Name', 'name')->label('Student name');

    expect($field->getPlaceholder())->toBe('Name');
});

it('is neither readonly nor disabled until asked', function () {
    $field = Input::make('Name', 'name');

    expect($field->isReadonly())->toBeFalse()
        ->and($field->isDisabled())->toBeFalse();

    $field->readonly()->disabled();

    expect($field->isReadonly())->toBeTrue()
        ->and($field->isDisabled())->toBeTrue();

    $field->readonly(false)->disabled(false);

    expect($field->isReadonly())->toBeFalse()
        ->and($field->isDisabled())->toBeFalse();
});

it('exposes readonly, disabled, help text and alert in toArray', function () {
    $array = Input::make('Name', 'name')
        ->readonly()
        ->disabled()
        ->help('As printed on the ID')
        ->alert('Changing it re-sends the welcome mail', 'warning')
        ->toArray(createNadotaRequest());

    expect($array['readonly'])->toBeTrue()
        ->and($array['disabled'])->toBeTrue()
        ->and($array['helpText'])->toBe('As printed on the ID')
        ->and($array['alert'])->toBe(['text' => 'Changing it re-sends the welcome mail', 'type' => 'warning']);
});

it('has no help text and no alert by default', function () {
    $field = Input::make('Name', 'name');

    expect($field->getHelpText())->toBeNull()
        ->and($field->getAlert())->toBeNull()
        ->and($field->getAlertText())->toBeNull()
        ->and($field->getAlertType())->toBe('info');
});

it('defaults the alert type to info', function () {
    expect(Input::make('Name', 'name')->alert('Heads up')->getAlert())
        ->toBe(['text' => 'Heads up', 'type' => 'info']);
});

it('reads protected properties through the magic getter and rejects unknown ones', function () {
    $field = Input::make('Name', 'name')->help('Help');

    expect($field->helpText)->toBe('Help')
        ->and($field->fieldData->attribute)->toBe('name')
        ->and(fn () => $field->doesNotExist)->toThrow(InvalidArgumentException::class);
});

it('does not fill a readonly or disabled field on the model', function () {
    $request = createNadotaRequest(['name' => 'Ana', 'email' => 'ana@example.com']);
    $model = new Student();

    Input::make('Name', 'name')->readonly()->fill($request, $model);
    Input::make('Email', 'email')->disabled()->fill($request, $model);

    expect($model->getAttributes())->toBe([]);
});

describe('through the persist pipeline', function () {
    beforeEach(fn () => FixtureSchema::create());

    $resource = fn () => AdHocResource::withFields([
        Input::make('Name', 'name'),
        Input::make('Email', 'email')->readonly(),
        Input::make('Website', 'website')->disabled(),
    ]);

    $request = function (array $input) use ($resource): NadotaRequest {
        $request = createNadotaRequest($input);
        $request->setResource($resource());

        return $request;
    };

    it('ignores a submitted readonly field on store', function () use ($request) {
        (new ResourceStoreService())->handle($request(['name' => 'Ana', 'email' => 'forged@example.com']));

        expect(Student::first()->email)->toBeNull();
    });

    it('ignores a submitted disabled field on store', function () use ($request) {
        (new ResourceStoreService())->handle($request(['name' => 'Ana', 'website' => 'https://forged.test']));

        expect(Student::first()->website)->toBeNull();
    })->skip('ISSUE HTTP-008: disabled fields are validated and persisted from the request');

    it('ignores a submitted disabled field on update', function () use ($request) {
        $student = Student::create(['name' => 'Ana', 'website' => 'https://kept.test']);

        $req = $request(['name' => 'Ana', 'website' => 'https://forged.test']);
        (new ResourceUpdateService())->handle($req, $student->id);

        expect($student->fresh()->website)->toBe('https://kept.test');
    })->skip('ISSUE HTTP-008: disabled fields are validated and persisted from the request');
});
