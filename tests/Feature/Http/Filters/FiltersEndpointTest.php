<?php

use SchoolAid\Nadota\Tests\Concerns\InteractsWithNadotaApi;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\FilterLabNoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\FilterLabStudentResource;

/**
 * GET /nadota-api/{resource}/resource/filters — the filter descriptors the
 * frontend renders. Ordering by label and the config/filters agreement are
 * covered in tests/Feature/Http/ResourceMetaEndpointsTest.php.
 */
uses(InteractsWithNadotaApi::class);

beforeEach(function () {
    $this->bootNadotaApi([...$this->fixtureResources, FilterLabStudentResource::class, FilterLabNoteResource::class]);

    // Descriptors of a resource keyed "key|label" (keys alone are not unique, see FLT-001).
    $this->descriptors = fn (string $resourceKey): array => collect(
        $this->getJson($this->apiUrl($resourceKey, 'filters'))->assertOk()->json('data')
    )->keyBy(fn ($filter) => $filter['key'] . '|' . $filter['label'])->all();
});

describe('StudentResource', function () {
    it('describes the field-generated filters', function () {
        $filters = ($this->descriptors)('student');

        expect($filters['age|Age'])->toBe([
            'key' => 'age', 'label' => 'Age', 'component' => 'FilterNumber', 'type' => 'number',
            'options' => [], 'value' => '', 'props' => ['isRange' => false],
            'isRange' => false, 'filterKeys' => ['value' => 'age'],
        ])->and($filters['birth_date|Birth Date'])->toMatchArray([
            'component' => 'FilterDate', 'type' => 'date', 'isRange' => false, 'filterKeys' => ['value' => 'birth_date'],
        ])->and($filters['is_active|Active'])->toMatchArray([
            'component' => 'FilterBoolean', 'type' => 'boolean',
            'options' => [['label' => 'Sí', 'value' => true], ['label' => 'No', 'value' => false]],
            'props' => ['trueValue' => 1, 'falseValue' => 0],
        ])->and($filters['status|Status'])->toMatchArray([
            'component' => 'FilterSelect', 'type' => 'select',
            'options' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'enrolled', 'label' => 'Enrolled'],
                ['value' => 'graduated', 'label' => 'Graduated'],
            ],
            'props' => ['translateLabels' => false],
        ]);
    });

    it('describes the BelongsTo filter with a live options endpoint on the current resource', function () {
        Grade::create(['name' => 'G1']);
        $grade = ($this->descriptors)('student')['grade_id|Grade'];

        expect($grade)->toMatchArray([
            'component' => 'FilterDynamicSelect',
            'type' => 'belongsTo',
            'endpoint' => '/nadota-api/student/resource/field/grade_id/options',
            'filterKeys' => ['value' => 'grade_id'],
        ])->and($grade['props'])->toBe([
            'valueField' => 'id', 'labelField' => 'name', 'multiple' => false, 'searchable' => true,
            'applyToQuery' => true, 'relation' => 'grade',
            'endpoint' => '/nadota-api/student/resource/field/grade_id/options',
        ]);

        $this->getJson($grade['endpoint'])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('options.0.label', 'G1');
    });

    it('describes the resource range filters with _from/_to request keys', function () {
        $filters = ($this->descriptors)('student');

        expect($filters['age|Age Range'])->toMatchArray([
            'component' => 'FilterNumberRange', 'type' => 'number', 'isRange' => true,
            'props' => ['isRange' => true], 'filterKeys' => ['from' => 'age_from', 'to' => 'age_to'],
        ])->and($filters['enrolled_at|Enrolled Between'])->toMatchArray([
            'component' => 'FilterDateRange', 'type' => 'date', 'isRange' => true,
            'filterKeys' => ['from' => 'enrolled_at_from', 'to' => 'enrolled_at_to'],
        ]);
    });

    it('serialises the "Status Filter" options in SelectFilter\'s [label => value] orientation', function () {
        // StudentResource passes its [value => label] STATUSES map, so labels and values come out swapped (FLT-015).
        expect(($this->descriptors)('student')['status|Status Filter']['options'])->toBe([
            ['label' => 'draft', 'value' => 'Draft'],
            ['label' => 'enrolled', 'value' => 'Enrolled'],
            ['label' => 'graduated', 'value' => 'Graduated'],
        ]);
    });

    it('does not list filters for fields that are not filterable', function () {
        $keys = collect($this->getJson($this->apiUrl('student', 'filters'))->json('data'))->pluck('key');

        expect($keys)->not->toContain('name')
            ->and($keys)->not->toContain('email')
            ->and($keys)->not->toContain('notes_exists')
            ->and($keys)->not->toContain('courses_count');
    });

    it('gives every listed filter a unique key', function () {
        $keys = collect($this->getJson($this->apiUrl('student', 'filters'))->json('data'))->pluck('key');

        expect($keys->duplicates()->all())->toBe([]);
    })->skip('ISSUE FLT-001: field filters and resource filters on the same column share a key ("age", "status")');
});

describe('other fixture resources', function () {
    it('describes a GradeResource number filter and its BelongsTo school filter', function () {
        $filters = ($this->descriptors)('grade');

        expect(array_keys($filters))->toBe(['level|Level', 'school_id|School'])
            ->and($filters['school_id|School']['endpoint'])->toBe('/nadota-api/grade/resource/field/school_id/options');
    });

    it('describes SchoolResource and CourseResource filters', function () {
        expect(($this->descriptors)('school')['city|City'])->toMatchArray(['component' => 'FilterText', 'type' => 'text'])
            ->and(($this->descriptors)('course')['credits|Credits'])->toMatchArray(['component' => 'FilterNumber']);
    });

    it('describes only the Toggle of NoteResource (its MorphTo is not filterable)', function () {
        expect(array_keys(($this->descriptors)('note')))->toBe(['pinned|Pinned']);
    });

    it('returns 404 for an unknown resource', function () {
        $this->getJson($this->apiUrl('nope', 'filters'))->assertNotFound();
    });
});

describe('FilterLabStudentResource', function () {
    it('lists field filters then resource filters, every key once', function () {
        $keys = collect($this->getJson($this->apiUrl('filter-lab-student', 'filters'))->assertOk()->json('data'))->pluck('key')->all();

        expect($keys)->toBe([
            'email', 'website', 'age', 'enrolled_at', 'class_time', 'accept_terms', 'gender', 'interests',
            'grade_id', 'profile', 'courses', 'notes', 'notes_exists', 'courses_count',
            'is_active', 'status', 'balance', 'school_id', 'pinned_notes_exists', 'favorite_color',
        ]);
    });

    it('describes text, range, boolean and exists filters', function () {
        $filters = ($this->descriptors)('filter-lab-student');

        expect($filters['email|Email'])->toMatchArray(['component' => 'FilterEmail', 'type' => 'email', 'filterKeys' => ['value' => 'email']])
            ->and($filters['age|Age'])->toMatchArray(['component' => 'FilterNumberRange', 'isRange' => true, 'filterKeys' => ['from' => 'age_from', 'to' => 'age_to']])
            ->and($filters['enrolled_at|Enrolled At'])->toMatchArray(['component' => 'FilterDateRange', 'type' => 'datetime', 'isRange' => true])
            ->and($filters['balance|Balance'])->toMatchArray(['component' => 'FilterText', 'type' => 'text', 'isRange' => true, 'filterKeys' => ['from' => 'balance_from', 'to' => 'balance_to']])
            ->and($filters['accept_terms|Accepts Terms'])->toMatchArray(['component' => 'FilterBoolean', 'type' => 'checkbox'])
            ->and($filters['is_active|Is Active'])->toMatchArray(['component' => 'FilterBoolean', 'type' => 'boolean'])
            ->and($filters['notes_exists|Has Notes'])->toMatchArray(['component' => 'FilterBoolean', 'type' => 'exists', 'options' => []])
            ->and($filters['pinned_notes_exists|Has Pinned Notes'])->toMatchArray(['component' => 'FilterBoolean', 'type' => 'exists']);
    });

    it('describes relation filters with their relation type and multiplicity', function () {
        $filters = ($this->descriptors)('filter-lab-student');

        expect($filters['profile|Profile']['props'])->toMatchArray(['relation' => 'profile', 'relationType' => 'hasOne', 'multiple' => false])
            ->and($filters['courses|Courses']['props'])->toMatchArray(['relation' => 'courses', 'relationType' => 'belongsToMany', 'multiple' => true])
            ->and($filters['notes|Notes']['props'])->toMatchArray(['relation' => 'notes', 'relationType' => 'morphMany', 'multiple' => true])
            ->and($filters['courses|Courses']['endpoint'])->toBe('/nadota-api/filter-lab-student/resource/field/courses/options');
    });

    it('advertises relation-filter option endpoints that answer', function () {
        foreach (['profile', 'courses', 'notes'] as $key) {
            $this->getJson(($this->descriptors)('filter-lab-student')["{$key}|" . ucfirst($key)]['endpoint'])
                ->assertOk()->assertJsonPath('success', true);
        }
    });

    it('advertises a working options endpoint for a resource-level DynamicSelectFilter', function () {
        $endpoint = ($this->descriptors)('filter-lab-student')['school_id|School']['endpoint'];

        $this->getJson($endpoint)->assertOk()->assertJsonPath('success', true);
    })->skip('ISSUE FLT-029: the endpoint is built as /field/{filter field}/options, but no field "school_id" exists on the resource ("Field not found")');

    it('copies Radio and CheckboxList options with label and value in the right place', function () {
        $filters = ($this->descriptors)('filter-lab-student');

        expect($filters['gender|Gender']['options'])->toBe([['label' => 'Female', 'value' => 'f'], ['label' => 'Male', 'value' => 'm']])
            ->and($filters['interests|Interests']['options'])->toBe([['label' => 'Art', 'value' => 'art'], ['label' => 'Math', 'value' => 'math']]);
    })->skip('ISSUE FLT-027: Radio/CheckboxList options reach SelectFilter as [value => label] and come out swapped');
});

describe('FilterLabNoteResource', function () {
    it('describes the MorphTo type/entity filter pair', function () {
        $filters = ($this->descriptors)('filter-lab-note');

        expect(array_keys($filters))->toBe(['pinned|Pinned', 'notable_type|Notable', 'notable_id|Notable'])
            ->and($filters['notable_type|Notable'])->toMatchArray([
                'component' => 'FilterSelect', 'type' => 'select',
                'options' => [['label' => 'Student', 'value' => 'student'], ['label' => 'Grade', 'value' => 'grade']],
            ])
            ->and($filters['notable_id|Notable'])->toMatchArray([
                'component' => 'FilterDynamicSelect', 'type' => 'dynamicSelect',
                'endpoint' => '/nadota-api/filter-lab-note/resource/field/notable/morph-options/{morphType}',
            ])
            ->and($filters['notable_id|Notable']['props'])->toMatchArray([
                'isMorphEndpoint' => true,
                'endpointTemplate' => '/nadota-api/filter-lab-note/resource/field/notable/morph-options/{morphType}',
                'dependsOn' => ['notable_type'],
                'filtersToSend' => ['notable_type'],
            ]);
    });

    it('advertises a morph-options template that answers once {morphType} is replaced', function () {
        Student::create(['name' => 'Ana']);
        $template = ($this->descriptors)('filter-lab-note')['notable_id|Notable']['props']['endpointTemplate'];

        $this->getJson(str_replace('{morphType}', 'student', $template))->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('options.0.label', 'Ana');
    });
});
