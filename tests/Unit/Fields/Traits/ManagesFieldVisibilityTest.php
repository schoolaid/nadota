<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\AdHocResource;

/**
 * Field selection per view context. Index/detail/create/update each read one
 * visibility flag; showWhen/hideWhen apply to every context.
 */

function visibilityKeys($fields): array
{
    return collect($fields)->map(fn ($field) => $field->key())->values()->all();
}

function visibilityContextResource(): AdHocResource
{
    return AdHocResource::withFields([
        Input::make('Everywhere', 'everywhere'),
        Input::make('Index Only', 'index_only')->onlyOnIndex(),
        Input::make('Detail Only', 'detail_only')->onlyOnDetail(),
        Input::make('Forms Only', 'forms_only')->onlyOnForms(),
        Input::make('Create Only', 'create_only')->onlyOnForms()->hideFromUpdate(),
        Input::make('Update Only', 'update_only')->onlyOnForms()->hideFromCreation(),
        Section::make('Extra', [
            Input::make('In Section', 'in_section'),
            Input::make('Section Detail', 'section_detail')->onlyOnDetail(),
        ]),
    ]);
}

it('flattens sections into one ordered field list', function () {
    expect(visibilityKeys(visibilityContextResource()->flattenFields(createNadotaRequest())))->toBe([
        'everywhere', 'index_only', 'detail_only', 'forms_only', 'create_only', 'update_only', 'in_section', 'section_detail',
    ]);
});

it('picks the fields of each view context', function (string $context, array $expected) {
    $resource = visibilityContextResource();
    $request = createNadotaRequest();
    $model = new Student();

    $fields = match ($context) {
        'index' => $resource->fieldsForIndex($request),
        'show' => $resource->fieldsForShow($request, 'show', $model),
        'show:update' => $resource->fieldsForShow($request, 'update', $model),
        'create' => $resource->fieldsForForm($request),
        'update' => $resource->fieldsForForm($request, $model),
    };

    expect(visibilityKeys($fields))->toBe($expected);
})->with([
    'index' => ['index', ['everywhere', 'index_only', 'in_section']],
    'detail' => ['show', ['everywhere', 'detail_only', 'in_section', 'section_detail']],
    'show with action=update' => ['show:update', ['everywhere', 'forms_only', 'update_only', 'in_section']],
    'create form' => ['create', ['everywhere', 'forms_only', 'create_only', 'in_section']],
    'update form' => ['update', ['everywhere', 'forms_only', 'update_only', 'in_section']],
]);

it('returns a re-indexed list', function () {
    $fields = visibilityContextResource()->fieldsForIndex(createNadotaRequest());

    expect($fields->keys()->all())->toBe([0, 1, 2]);
});

it('hands the request and the record to visibility callbacks', function () {
    $received = [];
    $resource = AdHocResource::withFields([
        Input::make('Grade', 'grade')->showOnUpdate(function ($request, $model) use (&$received) {
            $received[] = [$request, $model];

            return $model?->status === 'draft';
        }),
    ]);
    $request = createNadotaRequest();
    $draft = new Student(['status' => 'draft']);
    $enrolled = new Student(['status' => 'enrolled']);

    expect(visibilityKeys($resource->fieldsForForm($request, $draft)))->toBe(['grade'])
        ->and(visibilityKeys($resource->fieldsForForm($request, $enrolled)))->toBe([])
        ->and($received[0][0])->toBe($request)
        ->and($received[0][1])->toBe($draft);
});

it('applies showWhen and hideWhen in every context', function () {
    $resource = AdHocResource::withFields([
        Input::make('Shown', 'shown')->showWhen(fn ($request) => $request->get('role') === 'admin'),
        Input::make('Hidden', 'hidden')->hideWhen(fn ($request) => $request->get('role') === 'admin'),
    ]);
    $admin = createNadotaRequest(['role' => 'admin']);
    $guest = createNadotaRequest(['role' => 'guest']);

    expect(visibilityKeys($resource->fieldsForIndex($admin)))->toBe(['shown'])
        ->and(visibilityKeys($resource->fieldsForForm($admin)))->toBe(['shown'])
        ->and(visibilityKeys($resource->fieldsForShow($admin, 'show', new Student())))->toBe(['shown'])
        ->and(visibilityKeys($resource->fieldsForIndex($guest)))->toBe(['hidden']);
});

describe('structured sections', function () {
    it('wraps loose fields into default groups around the sections', function () {
        $layout = AdHocResource::withFields([
            Input::make('A', 'a'),
            Section::make('Middle', [Input::make('B', 'b')])->icon('star')->description('Mid')->collapsed(),
            Input::make('C', 'c'),
            Input::make('D', 'd'),
        ])->sectionsForForm(createNadotaRequest());

        expect($layout->all())->toHaveCount(3)
            ->and($layout[0]['type'])->toBe('default')
            ->and(visibilityKeys($layout[0]['fields']))->toBe(['a'])
            ->and($layout[1])->toMatchArray([
                'type' => 'section', 'title' => 'Middle', 'icon' => 'star',
                'description' => 'Mid', 'collapsible' => true, 'collapsed' => true,
            ])
            ->and(visibilityKeys($layout[2]['fields']))->toBe(['c', 'd']);
    });

    it('evaluates section and field callbacks against the record on the update form', function () {
        $resource = AdHocResource::withFields([
            Section::make('Graduation', [Input::make('Diploma', 'diploma')])
                ->showOnUpdate(fn ($request, $model) => $model?->status === 'graduated'),
        ]);

        expect($resource->sectionsForForm(createNadotaRequest(), new Student(['status' => 'graduated'])))->toHaveCount(1)
            ->and($resource->sectionsForForm(createNadotaRequest(), new Student(['status' => 'draft'])))->toHaveCount(0);
    });

    it('splits the loose fields around a hidden section into two groups', function () {
        // Current behaviour (see TRT-016): the loose-field buffer is flushed
        // before the section's own visibility is checked.
        $layout = AdHocResource::withFields([
            Input::make('A', 'a'),
            Section::make('Hidden', [Input::make('B', 'b')])->hideFromDetail(),
            Input::make('C', 'c'),
        ])->sectionsForShow(createNadotaRequest(), 'show');

        expect($layout->pluck('type')->all())->toBe(['default', 'default']);
    });
});

describe('getSectionsLayout()', function () {
    it('returns field keys only, per context', function () {
        $resource = visibilityContextResource();
        $request = createNadotaRequest();

        expect($resource->getSectionsLayout($request, 'create'))->toBe([
            ['type' => 'default', 'fieldKeys' => ['everywhere', 'forms_only', 'create_only']],
            ['type' => 'section', 'title' => 'Extra', 'icon' => null, 'description' => null,
                'collapsible' => false, 'collapsed' => false, 'fieldKeys' => ['in_section']],
        ])
            ->and($resource->getSectionsLayout($request, 'update')[0]['fieldKeys'])->toBe(['everywhere', 'forms_only', 'update_only'])
            ->and($resource->getSectionsLayout($request, 'detail')[1]['fieldKeys'])->toBe(['in_section', 'section_detail']);
    });

    it('treats an unknown context as detail', function () {
        $resource = visibilityContextResource();
        $request = createNadotaRequest();

        expect($resource->getSectionsLayout($request, 'whatever'))->toBe($resource->getSectionsLayout($request, 'detail'));
    });

    it('drops a section with no visible field', function () {
        $layout = AdHocResource::withFields([
            Section::make('Empty on create', [Input::make('X', 'x')->hideFromCreation()]),
        ])->getSectionsLayout(createNadotaRequest(), 'create');

        expect($layout)->toBe([]);
    });

    it('hands the request to visibility callbacks like the other field lists do', function () {
        $resource = AdHocResource::withFields([
            Input::make('Admin', 'admin_note')->showWhen(fn ($request) => $request?->get('role') === 'admin'),
        ]);

        expect($resource->getSectionsLayout(createNadotaRequest(['role' => 'admin']), 'detail'))
            ->toBe([['type' => 'default', 'fieldKeys' => ['admin_note']]]);
    })->skip('ISSUE TRT-003: getSectionsLayout() calls the visibility methods without the request');
});
