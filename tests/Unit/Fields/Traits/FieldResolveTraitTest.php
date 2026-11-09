<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Http\Services\ResourceStoreService;
use SchoolAid\Nadota\Http\Services\ResourceUpdateService;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

describe('resolve()', function () {
    it('returns the model attribute', function () {
        $value = Input::make('Name', 'name')->resolve(createNadotaRequest(), new Student(['name' => 'Ana']), null);

        expect($value)->toBe('Ana');
    });

    it('returns falsy model values untouched even when a default exists', function ($stored) {
        $model = new Student(['age' => $stored]);

        expect(Number::make('Age', 'age')->default(18)->resolve(createNadotaRequest(), $model, null))->toBe($stored);
    })->with([0, '', '0']);

    it('falls back to the default only when the attribute is null', function () {
        $field = Input::make('Status', 'status')->default('draft');

        expect($field->resolve(createNadotaRequest(), new Student(['status' => null]), null))->toBe('draft')
            ->and(Input::make('Status', 'status')->resolve(createNadotaRequest(), new Student(), null))->toBeNull();
    });

    it('lets a display callback win over the attribute and the default', function () {
        $resource = new StudentResource();
        $received = null;

        $field = Input::make('Name', 'name')
            ->default('unused')
            ->displayUsing(function ($model, $res) use (&$received) {
                $received = [$model, $res];

                return strtoupper($model->name);
            });

        $model = new Student(['name' => 'Ana']);

        expect($field->resolve(createNadotaRequest(), $model, $resource))->toBe('ANA')
            ->and($received[0])->toBe($model)
            ->and($received[1])->toBe($resource);
    });

    it('puts the resolved value in toArray only when a model is given', function () {
        $field = Input::make('Name', 'name');

        expect($field->toArray(createNadotaRequest()))->not->toHaveKey('value')
            ->and($field->toArray(createNadotaRequest(), new Student(['name' => 'Ana']))['value'])->toBe('Ana');
    });
});

describe('resolveForStore() / resolveForUpdate()', function () {
    it('passes a submitted value through', function (string $method) {
        $field = Input::make('Status', 'status')->default('draft');

        expect($field->{$method}(createNadotaRequest(), new Student(), null, 'enrolled'))->toBe('enrolled');
    })->with(['resolveForStore', 'resolveForUpdate']);

    it('replaces a null value with the default', function (string $method) {
        $field = Input::make('Status', 'status')->default('draft');

        expect($field->{$method}(createNadotaRequest(), new Student(), null, null))->toBe('draft');
    })->with(['resolveForStore', 'resolveForUpdate']);

    it('keeps null when there is no default', function (string $method) {
        expect(Input::make('Status', 'status')->{$method}(createNadotaRequest(), new Student(), null, null))->toBeNull();
    })->with(['resolveForStore', 'resolveForUpdate']);

    it('passes the request, model and resource to a default callback', function () {
        $request = createNadotaRequest();
        $model = new Student();
        $resource = new StudentResource();
        $received = null;

        Input::make('Status', 'status')
            ->default(function (...$args) use (&$received) {
                $received = $args;

                return 'computed';
            })
            ->resolveForStore($request, $model, $resource, null);

        expect($received)->toBe([$request, $model, $resource]);
    });

    it('keeps a submitted 0 or false instead of the default', function (string $method, mixed $submitted) {
        $field = Number::make('Age', 'age')->default(18);

        expect($field->{$method}(createNadotaRequest(), new Student(), null, $submitted))->toBe($submitted);
    })->with(['resolveForStore', 'resolveForUpdate'])->with([0, false])
        ->skip('ISSUE HTTP-009: loose `$value == null` swaps 0/false for the default');

    it('keeps a submitted "0" string (it is not loosely equal to null)', function (string $method) {
        expect(Number::make('Age', 'age')->default(18)->{$method}(createNadotaRequest(), new Student(), null, '0'))->toBe('0');
    })->with(['resolveForStore', 'resolveForUpdate']);
});

describe('through the persist pipeline', function () {
    beforeEach(fn () => FixtureSchema::create());

    $request = function (array $fields, array $input): NadotaRequest {
        $request = createNadotaRequest($input);
        $request->setResource(AdHocResource::withFields($fields));

        return $request;
    };

    it('stores the default when the attribute is sent as null', function () use ($request) {
        (new ResourceStoreService())->handle($request(
            [Input::make('Name', 'name'), Input::make('Status', 'status')->default('draft')],
            ['name' => 'Ana', 'status' => null],
        ));

        expect(Student::first()->status)->toBe('draft');
    });

    it('applies no default when the attribute is omitted on store', function () use ($request) {
        // Current behaviour, see HTTP-009: the default is a form pre-fill plus a
        // "null means default" rule, not a server-side default for missing keys.
        (new ResourceStoreService())->handle($request(
            [Input::make('Name', 'name'), Input::make('Status', 'status')->default('draft')],
            ['name' => 'Ana'],
        ));

        expect(Student::first()->status)->toBeNull();
    });

    it('stores a submitted 0 for a number field with a default', function () use ($request) {
        (new ResourceStoreService())->handle($request(
            [Input::make('Name', 'name'), Number::make('Age', 'age')->default(18)],
            ['name' => 'Ana', 'age' => 0],
        ));

        expect(Student::first()->age)->toBe(0);
    })->skip('ISSUE HTTP-009: 0 is stored as the default (18)');

    it('lets a toggle with default(true) be switched off on update', function () use ($request) {
        $student = Student::create(['name' => 'Ana', 'is_active' => true]);

        (new ResourceUpdateService())->handle($request(
            [Input::make('Name', 'name'), Toggle::make('Active', 'is_active')->default(true)],
            ['name' => 'Ana', 'is_active' => false],
        ), $student->id);

        expect($student->fresh()->is_active)->toBeFalse();
    })->skip('ISSUE HTTP-009: false is replaced by the default, the toggle can never be switched off');
});

describe('fields that override resolve()', function () {
    // FieldResolveTrait::resolve() gives every field a display callback and a
    // default fallback. These fields replace resolve() and drop both.
    dataset('resolve overrides', [
        'Select' => [fn () => \SchoolAid\Nadota\Http\Fields\Select::make('Status', 'status')->options(['draft' => 'Draft'])],
        'Status' => [fn () => \SchoolAid\Nadota\Http\Fields\Status::make('Status', 'status')],
        'Toggle' => [fn () => Toggle::make('Active', 'is_active')],
        'Checkbox' => [fn () => \SchoolAid\Nadota\Http\Fields\Checkbox::make('Terms', 'accept_terms')],
        'Date' => [fn () => \SchoolAid\Nadota\Http\Fields\Date::make('Birth', 'birth_date')],
        'DateTime' => [fn () => \SchoolAid\Nadota\Http\Fields\DateTime::make('Enrolled', 'enrolled_at')],
        'Time' => [fn () => \SchoolAid\Nadota\Http\Fields\Time::make('Class', 'class_time')],
        'Json' => [fn () => \SchoolAid\Nadota\Http\Fields\Json::make('Meta', 'meta')],
    ]);

    it('still honours a display callback', function ($field) {
        $field->displayUsing(fn () => 'computed');

        expect($field->resolve(createNadotaRequest(), new Student(), null))->toBe('computed');
    })->with('resolve overrides')
        ->skip('ISSUE TRT-005: resolve() overrides ignore displayUsing()');

    it('still falls back to the default for a null column', function () {
        $field = \SchoolAid\Nadota\Http\Fields\Select::make('Status', 'status')->options(['draft' => 'Draft'])->default('draft');

        expect($field->resolve(createNadotaRequest(), new Student(), null))->toBe('draft');
    })->skip('ISSUE TRT-005: resolve() overrides ignore the default');
});
