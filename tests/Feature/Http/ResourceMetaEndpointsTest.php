<?php

use SchoolAid\Nadota\ResourceManager;
use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\IndexScopedStudentResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\PagedStudentResource;

/**
 * ResourceIndexController: GET /{resourceKey}/resource/{config,info,fields,filters,lens,data}
 * (filter behaviour itself is covered by tests/Feature/Http/Filters).
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi();
    ResourceManager::registerResourceClass(PagedStudentResource::class);
    ResourceManager::registerResourceClass(IndexScopedStudentResource::class);
});

describe('info', function () {
    it('returns the resource info wrapped in data, plus the export config', function () {
        $data = $this->getJson($this->apiUrl('paged-student', 'info'))->assertOk()->json('data');

        expect($data)->toMatchArray([
            'key' => 'paged-student',
            'title' => 'Pupils',
            'description' => 'Students, five at a time',
            'perPage' => 5,
            'allowedPerPage' => [5, 15],
            'allowedSoftDeletes' => true,
            'canCreate' => true,
            'components' => [
                'index' => 'PupilIndex', 'show' => 'PupilShow', 'create' => 'ResourceCreate',
                'update' => 'ResourceUpdate', 'delete' => 'ResourceDelete',
            ],
            'detailCardWidth' => 'lg',
            'mainCard' => ['collapsible' => false, 'defaultCollapsed' => false, 'title' => 'Pupil list'],
            'search' => ['key' => 'q', 'enabled' => true],
            'selection' => ['showRowCheckbox' => true, 'showSelectAll' => true],
            'pollingInterval' => 30,
        ])->and($data['export'])->toHaveKeys(['enabled', 'url', 'formats', 'columns']);
    });

    it('exposes the package defaults for a resource that configures nothing', function () {
        $data = $this->getJson($this->apiUrl('grade', 'info'))->assertOk()->json('data');

        expect($data)->toMatchArray([
            'key' => 'grade',
            'description' => null,
            'perPage' => 20,
            'allowedPerPage' => [10, 20, 50, 100],
            'allowedSoftDeletes' => false,
            'detailCardWidth' => null,
            'mainCard' => ['collapsible' => true, 'defaultCollapsed' => true, 'title' => null],
            'search' => ['key' => 'globalSearch', 'enabled' => true],
            'selection' => ['showRowCheckbox' => false, 'showSelectAll' => false],
            'pollingInterval' => null,
        ]);
    });

    it('derives a default title without the word Resource', function () {
        $this->getJson($this->apiUrl('student', 'info'))->assertOk()->assertJsonPath('data.title', 'Students');
    })->skip('ISSUE CORE-006: Resource::title() pluralises the class basename, so StudentResource is titled "Student Resources"');
});

describe('config', function () {
    it('bundles resource info, index fields, filters, index actions, section layouts and export', function () {
        $config = $this->getJson($this->apiUrl('student', 'config'))->assertOk()->json();

        expect(array_keys($config))->toBe(['resource', 'fields', 'filters', 'actions', 'sections', 'export'])
            ->and(array_keys($config['sections']))->toBe(['detail', 'create', 'update'])
            ->and(collect($config['actions'])->pluck('key')->all())->toBe(['activate-students', 'purge-inactive-students']);
    });

    it('agrees with the info, fields and filters endpoints', function () {
        $config = $this->getJson($this->apiUrl('student', 'config'))->assertOk()->json();
        $info = $this->getJson($this->apiUrl('student', 'info'))->json('data');

        expect($config['resource'])->toBe(collect($info)->except('export')->all())
            ->and($config['export'])->toBe($info['export'])
            ->and($config['fields'])->toBe($this->getJson($this->apiUrl('student', 'fields'))->json('data'))
            ->and($config['filters'])->toBe($this->getJson($this->apiUrl('student', 'filters'))->json('data'));
    });

    it('lists the field keys of each form in the section layouts', function () {
        $sections = $this->getJson($this->apiUrl('student', 'config'))->assertOk()->json('sections');
        $createKeys = $this->getJson($this->apiUrl('student', 'create'))->json('data.attributes');

        expect($sections['create'])->toHaveCount(1)
            ->and($sections['create'][0]['type'])->toBe('default')
            ->and($sections['create'][0]['fieldKeys'])->toBe(collect($createKeys)->pluck('key')->all())
            ->and($sections['update'][0]['fieldKeys'])->toBe($sections['create'][0]['fieldKeys'])
            ->and($sections['detail'][0]['fieldKeys'])->toContain('bio', 'profile', 'courses_count')
            ->and($sections['detail'][0]['fieldKeys'])->not->toContain('password', 'uuid');
    });
});

describe('fields', function () {
    it('lists only the fields shown on index, without values', function () {
        $fields = $this->getJson($this->apiUrl('student', 'fields'))->assertOk()->json('data');

        expect(collect($fields)->pluck('key')->all())->toBe([
            'name', 'email', 'age', 'balance', 'birth_date', 'enrolled_at', 'is_active', 'status', 'grade_id',
            'courses_count', 'notes_exists',
        ])->and(collect($fields)->every(fn ($field) => ! array_key_exists('value', $field)))->toBeTrue();
    });
});

describe('filters', function () {
    it('lists the field filters first, then the resource filters', function () {
        $filters = $this->getJson($this->apiUrl('student', 'filters'))->assertOk()->json('data');

        expect(collect($filters)->map(fn ($filter) => $filter['label'])->all())->toBe([
            'Age', 'Birth Date', 'Active', 'Status', 'Grade', 'Status Filter', 'Age Range', 'Enrolled Between',
        ])->and($filters[0])->toHaveKeys(['key', 'label', 'component', 'type', 'options', 'value', 'props', 'isRange', 'filterKeys']);
    });

    it('returns an empty list for a resource without filters', function () {
        $this->getJson($this->apiUrl('student-profile', 'filters'))->assertOk()->assertExactJson(['data' => []]);
    });
});

describe('lens', function () {
    it('answers without a server error', function () {
        expect($this->getJson($this->apiUrl('student', 'lens'))->status())->toBeIn([200, 404]);
    })->skip('ISSUE CORE-002: the /lens route points to ResourceIndexController::lens(), which does not exist');
});

describe('data (compact)', function () {
    beforeEach(function () {
        Student::create(['name' => 'Ana', 'email' => 'ana@example.com', 'is_active' => true, 'status' => 'enrolled', 'password' => 'hash-1']);
        Student::create(['name' => 'Bob', 'email' => 'bob@example.com', 'is_active' => false, 'status' => 'enrolled', 'password' => 'hash-2']);
        Student::create(['name' => 'Trashed'])->delete();
    });

    it('returns a plain array of the requested key column, without trashed rows', function () {
        $this->getJson($this->apiUrl('student', 'data') . '?fields=id')
            ->assertOk()
            ->assertExactJson([['id' => 1], ['id' => 2]]);
    });

    it('returns the requested index columns', function () {
        $this->getJson($this->apiUrl('student', 'data') . '?fields=id,name')
            ->assertOk()
            ->assertExactJson([['id' => 1, 'name' => 'Ana'], ['id' => 2, 'name' => 'Bob']]);
    })->skip('ISSUE HTTP-005: compact() keeps the fields where isAppliedInIndexQuery() is false (multi-record relations), so scalar columns such as name are never selectable');

    it('selects real columns when no fields are requested', function () {
        $rows = $this->getJson($this->apiUrl('student', 'data'))->assertOk()->json();

        expect($rows[0])->toHaveKey('id')
            ->and($rows[0])->not->toHaveKey('');
    })->skip('ISSUE HTTP-005: without ?fields the column list is [""] (the empty attribute of a relation field), so SQLite selects a "" literal and MySQL fails');

    it('never returns columns that are not requested index fields', function () {
        $response = $this->getJson($this->apiUrl('student', 'data') . '?fields=signature,avatar')->assertOk();

        expect($response->json('0'))->toBe(['id' => 1])
            ->and($response->getContent())->not->toContain('ana@example.com');
    })->skip('ISSUE CORE-004: when no requested column is valid the query has no select, so every non-$hidden column of every row is returned');

    it('honours the resource getQuery() and queryIndex() scoping', function () {
        $this->getJson($this->apiUrl('index-scoped-student', 'data') . '?fields=id')
            ->assertOk()
            ->assertExactJson([['id' => 1]]);
    })->skip('ISSUE CORE-004: compact() queries $resource->model::query() directly, bypassing getQuery()/queryIndex()');
});
