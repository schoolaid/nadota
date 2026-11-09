<?php

use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Fields\Count;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Http\Services\Traits\ProcessesFields;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

/**
 * ProcessesFields is the field-selection and rule-building half of the
 * store/update pipeline (AbstractResourcePersistService). The pipeline itself
 * is exercised in tests/ServiceIntegration/*.
 */

function processesFieldsHost(): object
{
    return new class {
        use ProcessesFields;

        public function __call($method, $arguments)
        {
            return $this->{$method}(...$arguments);
        }
    };
}

function processesFieldsKeys($fields): array
{
    return collect($fields)->map(fn ($field) => $field->key())->values()->all();
}

function processesFieldsStudent(int $id = 7): Student
{
    $student = new Student(['name' => 'Ana']);
    $student->id = $id;
    $student->exists = true;

    return $student;
}

describe('field selection', function () {
    $resource = fn () => AdHocResource::withFields([
        Input::make('Name', 'name'),
        Input::make('Create Only', 'create_only')->hideFromUpdate(),
        Input::make('Update Only', 'update_only')->hideFromCreation(),
        Input::make('Locked', 'locked')->readonly(),
        Input::make('Shout', 'shout')->displayUsing(fn ($model) => 'x'),
        Count::make('Courses', 'courses'),
        Section::make('More', [Input::make('In Section', 'in_section')]),
    ]);

    it('keeps the creation fields that are writable', function () use ($resource) {
        expect(processesFieldsKeys(processesFieldsHost()->filterFieldsForStore($resource(), createNadotaRequest())))
            ->toBe(['name', 'create_only', 'in_section']);
    });

    it('keeps the update fields that are writable', function () use ($resource) {
        expect(processesFieldsKeys(processesFieldsHost()->filterFieldsForUpdate($resource(), createNadotaRequest())))
            ->toBe(['name', 'update_only', 'in_section']);
    });

    it('evaluates request-dependent visibility with the request', function () {
        $resource = AdHocResource::withFields([
            Input::make('Admin', 'admin_note')->showOnCreation(fn ($request) => $request?->get('role') === 'admin'),
        ]);

        expect(processesFieldsKeys(processesFieldsHost()->filterFieldsForStore($resource, createNadotaRequest(['role' => 'admin']))))->toBe(['admin_note'])
            ->and(processesFieldsKeys(processesFieldsHost()->filterFieldsForStore($resource, createNadotaRequest())))->toBe([]);
    });

    it('drops disabled fields like readonly ones', function () {
        $resource = AdHocResource::withFields([Input::make('Name', 'name'), Input::make('Locked', 'locked')->disabled()]);

        expect(processesFieldsKeys(processesFieldsHost()->filterFieldsForStore($resource, createNadotaRequest())))->toBe(['name']);
    })->skip('ISSUE HTTP-008: disabled fields are not filtered out, so they are validated and written');
});

describe('validation rules', function () {
    it('keys the rules by attribute', function () {
        $rules = processesFieldsHost()->buildValidationRules(collect([
            Input::make('Name', 'name')->required()->rules(['max:50']),
            Input::make('Email', 'email'),
        ]));

        expect($rules)->toBe(['name' => ['required', 'max:50'], 'email' => []]);
    });

    it('uses creation rules on store and update rules on update', function () {
        $fields = collect([Input::make('Password', 'password')->required()->updateRules(['nullable'])]);

        expect(processesFieldsHost()->buildValidationRules($fields)['password'])->toBe(['required'])
            ->and(processesFieldsHost()->buildValidationRules($fields, processesFieldsStudent())['password'])->toBe(['nullable']);
    });

    it('replaces :id with the record key on update only', function () {
        $fields = collect([Input::make('Email', 'email')->rules(['unique:fx_students,email,:id'])]);

        expect(processesFieldsHost()->buildValidationRules($fields, processesFieldsStudent(7))['email'])->toBe(['unique:fx_students,email,7'])
            ->and(processesFieldsHost()->buildValidationRules($fields)['email'])->toBe(['unique:fx_students,email,:id']);
    });

    it('leaves closures and rule objects untouched when replacing :id', function () {
        $closure = fn ($attribute, $value, $fail) => null;
        $fields = collect([Input::make('Email', 'email')->rules([$closure, 'max:5'])]);

        $rules = processesFieldsHost()->buildValidationRules($fields, processesFieldsStudent())['email'];

        expect($rules)->toContain($closure)->toContain('max:5');
    });

    it('adds the nested rules a field declares', function () {
        $tags = new class('Tags', 'tags') extends Input {
            public function getNestedRules(): array
            {
                return ['tags.*' => ['string', 'distinct:ignore_case', 'not_in::id']];
            }
        };

        $store = processesFieldsHost()->buildValidationRules(collect([$tags->rules(['array'])]));
        $update = processesFieldsHost()->buildValidationRules(collect([$tags]), processesFieldsStudent(3));

        expect($store)->toBe(['tags' => ['array'], 'tags.*' => ['string', 'distinct:ignore_case', 'not_in::id']])
            ->and($update['tags.*'])->toBe(['string', 'distinct:ignore_case', 'not_in:3']);
    });

    it('puts unkeyed MorphTo rules on the id column', function () {
        $field = MorphTo::make('Notable', 'notable')->rules(['required', 'integer']);

        expect(processesFieldsHost()->buildValidationRules(collect([$field])))->toBe(['notable_id' => ['required', 'integer']]);
    });

    it('adds no rules for a MorphTo without rules', function () {
        expect(processesFieldsHost()->buildValidationRules(collect([MorphTo::make('Notable', 'notable')])))->toBe([]);
    });

    it('applies MorphTo rules keyed by the type and id columns', function () {
        $field = MorphTo::make('Notable', 'notable')->rules([
            'notable_type' => 'required|in:student,grade',
            'notable_id' => 'required|integer',
        ]);

        expect(processesFieldsHost()->buildValidationRules(collect([$field])))->toBe([
            'notable_type' => 'required|in:student,grade',
            'notable_id' => 'required|integer',
        ]);
    })->skip('ISSUE TRT-018: ValidationTrait::getRules() drops string keys, so the keyed MorphTo branch never matches');

    it('honours MorphTo update rules', function () {
        $field = MorphTo::make('Notable', 'notable')->rules(['required'])->updateRules(['nullable']);

        expect(processesFieldsHost()->buildValidationRules(collect([$field]), processesFieldsStudent())['notable_id'])->toBe(['nullable']);
    })->skip('ISSUE TRT-018: addMorphToRules() reads getRules(), creationRules()/updateRules() are ignored');
});

describe('helpers', function () {
    it('collects the custom messages of every field', function () {
        $messages = processesFieldsHost()->buildValidationMessages(collect([
            Input::make('Name', 'name')->validationMessages(['required' => 'Name please']),
            Input::make('Email', 'email')->validationMessages(['email.email' => 'Not an e-mail']),
        ]));

        expect($messages)->toBe(['name.required' => 'Name please', 'email.email' => 'Not an e-mail']);
    });

    it('collects the request attributes, both columns for a MorphTo', function () {
        $attributes = processesFieldsHost()->collectFieldAttributes(collect([
            Input::make('Name', 'name'),
            MorphTo::make('Notable', 'notable'),
        ]));

        expect($attributes)->toBe(['name', 'notable_type', 'notable_id']);
    });

    it('selects the fields that write after the save', function () {
        $fields = collect([
            Input::make('Name', 'name'),
            BelongsToMany::make('Courses', 'courses', CourseResource::class),
        ]);

        expect(processesFieldsKeys(processesFieldsHost()->getAfterSaveFields($fields)))->toBe(['courses']);
    });

    it('replaces :id in a single string rule too', function () {
        expect(processesFieldsHost()->replaceIdPlaceholder('unique:t,c,:id', processesFieldsStudent(9)))->toBe('unique:t,c,9')
            ->and(processesFieldsHost()->replaceIdPlaceholder(null, processesFieldsStudent()))->toBeNull();
    });
});
