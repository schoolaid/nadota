<?php

/**
 * The filters a field hands to the pipeline once it is ->filterable():
 * FilterableTrait::filters() (type → filter mapping, copied options/values)
 * and the Exists::filters() override. The filterable flags themselves are
 * covered in tests/Unit/Fields/Traits/FilterableTraitTest.php.
 */

use SchoolAid\Nadota\Http\Fields\ArrayField;
use SchoolAid\Nadota\Http\Fields\Checkbox;
use SchoolAid\Nadota\Http\Fields\CheckboxList;
use SchoolAid\Nadota\Http\Fields\Code;
use SchoolAid\Nadota\Http\Fields\Color;
use SchoolAid\Nadota\Http\Fields\Count;
use SchoolAid\Nadota\Http\Fields\Currency;
use SchoolAid\Nadota\Http\Fields\Date;
use SchoolAid\Nadota\Http\Fields\DateTime;
use SchoolAid\Nadota\Http\Fields\Email;
use SchoolAid\Nadota\Http\Fields\Exists;
use SchoolAid\Nadota\Http\Fields\File;
use SchoolAid\Nadota\Http\Fields\Hidden;
use SchoolAid\Nadota\Http\Fields\Html;
use SchoolAid\Nadota\Http\Fields\Image;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Json;
use SchoolAid\Nadota\Http\Fields\KeyValue;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Password;
use SchoolAid\Nadota\Http\Fields\Radio;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasOne;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphOne;
use SchoolAid\Nadota\Http\Fields\Relations\MorphTo;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Status;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Fields\Time;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Fields\URL;
use SchoolAid\Nadota\Http\Filters\BooleanFilter;
use SchoolAid\Nadota\Http\Filters\DateFilter;
use SchoolAid\Nadota\Http\Filters\DefaultFilter;
use SchoolAid\Nadota\Http\Filters\DynamicSelectFilter;
use SchoolAid\Nadota\Http\Filters\ExistsFilter;
use SchoolAid\Nadota\Http\Filters\MorphTypeFilter;
use SchoolAid\Nadota\Http\Filters\NumberFilter;
use SchoolAid\Nadota\Http\Filters\RangeFilter;
use SchoolAid\Nadota\Http\Filters\RelationFilter;
use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\CourseResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\GradeResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\NoteResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentProfileResource;
use SchoolAid\Nadota\Tests\Fixtures\Resources\StudentResource;

function onlyFieldFilter($field)
{
    $filters = $field->filters();
    expect($filters)->toHaveCount(1);

    return $filters[0];
}

it('generates no filter for a field that is not filterable', function () {
    expect(Number::make('Age', 'age')->filters())->toBe([]);
});

// --- scalar fields ------------------------------------------------------------------------

it('maps text-like fields to a LIKE DefaultFilter carrying the field type', function (Closure $make, string $type) {
    $field = $make();
    $filter = onlyFieldFilter($field->filterable());

    expect($filter)->toBeInstanceOf(DefaultFilter::class)
        ->and($filter->type)->toBe($type)
        ->and($filter->component())->toBe('Filter' . ucfirst($type))
        ->and($filter->key())->toBe($field->getAttribute());
})->with([
    'input' => [fn () => Input::make('Name', 'name'), 'text'],
    'textarea' => [fn () => Textarea::make('Bio', 'bio'), 'textarea'],
    'email' => [fn () => Email::make('Email', 'email'), 'email'],
    'url' => [fn () => URL::make('Website', 'website'), 'url'],
    'password' => [fn () => Password::make('Password', 'password'), 'password'],
]);

it('labels the generated filter with the field label', function () {
    expect(onlyFieldFilter(Input::make('Full Name', 'name')->filterable())->name())->toBe('Full Name');
});

it('maps Number to an exact NumberFilter, or a range one with filterableRange()', function () {
    $exact = onlyFieldFilter(Number::make('Age', 'age')->filterable());
    $range = onlyFieldFilter(Number::make('Age', 'age')->filterableRange());

    expect($exact)->toBeInstanceOf(NumberFilter::class)
        ->and($exact->isRange())->toBeFalse()
        ->and($exact->component())->toBe('FilterNumber')
        ->and($range)->toBeInstanceOf(NumberFilter::class)
        ->and($range->isRange())->toBeTrue()
        ->and($range->component())->toBe('FilterNumberRange')
        ->and($range->getFilterKeys())->toBe(['from' => 'age_from', 'to' => 'age_to']);
});

it('maps Date, DateTime and Time to a DateFilter of the field type', function (Closure $make, string $type) {
    $single = onlyFieldFilter($make()->filterable());
    $range = onlyFieldFilter($make()->filterableRange());

    expect($single)->toBeInstanceOf(DateFilter::class)
        ->and($single->type)->toBe($type)
        ->and($single->isRange())->toBeFalse()
        ->and($range)->toBeInstanceOf(DateFilter::class)
        ->and($range->isRange())->toBeTrue()
        ->and($range->component())->toBe('FilterDateRange');
})->with([
    'date' => [fn () => Date::make('Birth Date', 'birth_date'), 'date'],
    'datetime' => [fn () => DateTime::make('Enrolled At', 'enrolled_at'), 'datetime'],
    'time' => [fn () => Time::make('Class Time', 'class_time'), 'time'],
]);

it('maps any other field made filterableRange() to a generic RangeFilter', function () {
    $filter = onlyFieldFilter(Currency::make('Balance', 'balance')->filterableRange());

    expect($filter)->toBeInstanceOf(RangeFilter::class)
        ->and($filter->type)->toBe('currency')
        ->and($filter->getFilterKeys())->toBe(['from' => 'balance_from', 'to' => 'balance_to']);
});

it('maps Toggle and Checkbox to a BooleanFilter with the field true/false values', function () {
    $toggle = onlyFieldFilter(Toggle::make('Active', 'is_active')->filterable());
    $custom = onlyFieldFilter(Checkbox::make('Terms', 'accept_terms')->trueValue('Y')->falseValue('N')->filterable());

    expect($toggle)->toBeInstanceOf(BooleanFilter::class)
        ->and($toggle->type)->toBe('boolean')
        ->and($toggle->props())->toBe(['trueValue' => 1, 'falseValue' => 0])
        ->and($custom)->toBeInstanceOf(BooleanFilter::class)
        ->and($custom->type)->toBe('checkbox')
        ->and($custom->props())->toBe(['trueValue' => 'Y', 'falseValue' => 'N']);
});

it('maps Select to a SelectFilter with the field options', function () {
    $filter = onlyFieldFilter(Select::make('Status', 'status')->options(['draft' => 'Draft', 'enrolled' => 'Enrolled'])->filterable());

    expect($filter)->toBeInstanceOf(SelectFilter::class)
        ->and($filter->type)->toBe('select')
        ->and($filter->toArray(createNadotaRequest())['options'])->toBe([
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'enrolled', 'label' => 'Enrolled'],
        ]);
});

it('maps Radio and CheckboxList to a SelectFilter', function ($field) {
    expect(onlyFieldFilter($field->options(['f' => 'Female'])->filterable()))->toBeInstanceOf(SelectFilter::class);
})->with([
    'radio' => fn () => Radio::make('Gender', 'gender'),
    'checkbox list' => fn () => CheckboxList::make('Interests', 'interests'),
]);

it('copies Radio and CheckboxList options with value and label in the right place', function ($field) {
    $filter = onlyFieldFilter($field->options(['draft' => 'Draft', 'enrolled' => 'Enrolled'])->filterable());

    expect($filter->toArray(createNadotaRequest())['options'])->toBe([
        ['label' => 'Draft', 'value' => 'draft'],
        ['label' => 'Enrolled', 'value' => 'enrolled'],
    ]);
})->with([
    'radio' => fn () => Radio::make('Status', 'status'),
    'checkbox list' => fn () => CheckboxList::make('Status', 'status'),
])->skip('ISSUE FLT-027: neither field overrides getOptions(), so the raw [value => label] map is read by reflection and SelectFilter treats it as [label => value]');

it('generates no filter for field types that cannot be filtered', function ($field) {
    expect($field->filterable()->filters())->toBe([]);
})->with([
    'hidden' => fn () => Hidden::make('UUID', 'uuid'),
    'file' => fn () => File::make('Document', 'document'),
    'image' => fn () => Image::make('Avatar', 'avatar'),
    'json' => fn () => Json::make('Meta', 'meta'),
    'code' => fn () => Code::make('Snippet', 'snippet'),
    'key value' => fn () => KeyValue::make('Settings', 'settings'),
    'array' => fn () => ArrayField::make('Skills', 'skills'),
    'html' => fn () => Html::make('Notes', 'html_notes'),
]);

it('falls back to a LIKE DefaultFilter for numeric-looking and enumerated types', function ($field) {
    // Currency, Status and Color end up in the `default` arm (FLT-024).
    expect(onlyFieldFilter($field->filterable()))->toBeInstanceOf(DefaultFilter::class);
})->with([
    'currency' => fn () => Currency::make('Balance', 'balance'),
    'status' => fn () => Status::make('Status', 'status'),
    'color' => fn () => Color::make('Color', 'favorite_color'),
]);

it('maps Count to a NumberFilter on the aggregate alias', function () {
    // The alias only exists in the SELECT list; see FLT-011 for what happens on the index.
    $filter = onlyFieldFilter(Count::make('Courses Count', 'courses')->filterable());

    expect($filter)->toBeInstanceOf(NumberFilter::class)
        ->and($filter->key())->toBe('courses_count');
});

it('maps Exists to an ExistsFilter over its relation instead of a BooleanFilter', function () {
    $filter = onlyFieldFilter(Exists::make('Has Notes', 'notes')->filterable());

    expect($filter)->toBeInstanceOf(ExistsFilter::class)
        ->and($filter->key())->toBe('notes_exists')
        ->and($filter->getRelation())->toBe('notes')
        ->and($filter->component())->toBe('FilterBoolean');
});

it('keeps the Exists constraint on the generated filter', function () {
    $constraint = fn ($q) => $q->where('pinned', true);

    expect(onlyFieldFilter(Exists::make('Has Pinned', 'notes')->constraint($constraint)->filterable())->getConstraint())->toBe($constraint);
});

// --- relation fields ----------------------------------------------------------------------

it('maps BelongsTo to a searchable DynamicSelectFilter on the foreign key that filters through the relation', function () {
    $filter = onlyFieldFilter(BelongsTo::make('Grade', 'grade', GradeResource::class)->filterable());

    expect($filter)->toBeInstanceOf(DynamicSelectFilter::class)
        ->and($filter->key())->toBe('grade_id')
        ->and($filter->type)->toBe('belongsTo')
        ->and($filter->props())->toMatchArray(['relation' => 'grade', 'labelField' => 'name', 'searchable' => true, 'multiple' => false]);
});

it('uses the display attribute of a BelongsTo as the option label field', function () {
    $filter = onlyFieldFilter(BelongsTo::make('Grade', 'grade', GradeResource::class)->displayAttribute('level')->filterable());

    expect($filter->props()['labelField'])->toBe('level');
});

it('keys a BelongsTo filter with a custom foreign key by the field key, not the foreign key', function () {
    // foreignKey() rewrites the attribute only; the filter is keyed by key() and filters through whereHas.
    $filter = onlyFieldFilter(BelongsTo::make('Grade', 'grade', GradeResource::class)->foreignKey('current_grade_id')->filterable());

    expect($filter->key())->toBe('grade_id');
});

it('advertises on the field the same request key its generated filter listens to', function ($field) {
    $filter = onlyFieldFilter($field->filterable());

    expect($field->getFilterKeys())->toBe($filter->getFilterKeys());
})->with([
    'belongs to with foreign key' => fn () => BelongsTo::make('Grade', 'grade', GradeResource::class)->foreignKey('current_grade_id'),
    'has many' => fn () => HasMany::make('Students', 'students', StudentResource::class),
    'belongs to many' => fn () => BelongsToMany::make('Courses', 'courses', CourseResource::class),
])->skip('ISSUE FLT-028: FilterableTrait::getFilterKeys() uses the attribute ("current_grade_id", ""), the generated filters use key()');

it('filters students by grade through the BelongsTo filter', function () {
    FixtureSchema::create();
    $g1 = Grade::create(['name' => 'G1']);
    $g2 = Grade::create(['name' => 'G2']);
    Student::create(['name' => 'Ana', 'grade_id' => $g1->id]);
    Student::create(['name' => 'Bob', 'grade_id' => $g2->id]);

    $filter = onlyFieldFilter(BelongsTo::make('Grade', 'grade', GradeResource::class)->filterable());

    expect($filter->apply(createNadotaRequest(), Student::query(), (string) $g2->id)->pluck('name')->all())->toBe(['Bob']);
});

it('maps to-many and to-one relations to a RelationFilter keyed by the relation', function (Closure $make, string $key, string $relationType, bool $multiple) {
    $field = $make();
    $filter = onlyFieldFilter($field->filterable());

    expect($filter)->toBeInstanceOf(RelationFilter::class)
        ->and($filter->key())->toBe($key)
        ->and($filter->props())->toMatchArray([
            'relation' => $field->getRelation(),
            'relationType' => $relationType,
            'multiple' => $multiple,
            'searchable' => true,
        ]);
})->with([
    'has many' => [fn () => HasMany::make('Students', 'students', StudentResource::class), 'students', 'hasMany', true],
    'has one' => [fn () => HasOne::make('Profile', 'profile', StudentProfileResource::class), 'profile', 'hasOne', false],
    'belongs to many' => [fn () => BelongsToMany::make('Courses', 'courses', CourseResource::class), 'courses', 'belongsToMany', true],
    'morph many' => [fn () => MorphMany::make('Notes', 'notes', NoteResource::class), 'notes', 'morphMany', true],
]);

it('maps MorphOne to a single-value RelationFilter', function () {
    $filter = onlyFieldFilter(MorphOne::make('Latest Note', 'latestNote', NoteResource::class)->filterable());

    expect($filter)->toBeInstanceOf(RelationFilter::class)
        ->and($filter->props())->toMatchArray(['relation' => 'latestNote', 'relationType' => 'morphOne', 'multiple' => false]);
});

it('keys a MorphOne filter by its relation, like the other relation filters', function () {
    expect(onlyFieldFilter(MorphOne::make('Latest Note', 'latestNote', NoteResource::class)->filterable())->key())->toBe('latestNote');
})->skip('ISSUE FLT-026: MorphOne leaves its field key empty, so the filter falls back to a key derived from the label ("latestnote")');

it('hides every relation field except BelongsTo from the index by default', function ($field) {
    // Together with FLT-004 this means their RelationFilter is listed by /filters but never applied by the index.
    expect($field->isShowOnIndex(createNadotaRequest(), null))->toBeFalse();
})->with([
    'has many' => fn () => HasMany::make('Students', 'students', StudentResource::class),
    'has one' => fn () => HasOne::make('Profile', 'profile', StudentProfileResource::class),
    'belongs to many' => fn () => BelongsToMany::make('Courses', 'courses', CourseResource::class),
    'morph many' => fn () => MorphMany::make('Notes', 'notes', NoteResource::class),
    'morph one' => fn () => MorphOne::make('Latest Note', 'latestNote', NoteResource::class),
]);

it('maps MorphTo to a type filter and an entity filter built from its morph resources', function () {
    $filters = MorphTo::make('Notable', 'notable', [
        'student' => StudentResource::class,
        'grade' => GradeResource::class,
    ])->filterable()->filters();

    expect($filters)->toHaveCount(2)
        ->and($filters[0])->toBeInstanceOf(MorphTypeFilter::class)
        ->and($filters[0]->key())->toBe('notable_type')
        ->and($filters[1])->toBeInstanceOf(DynamicSelectFilter::class)
        ->and($filters[1]->key())->toBe('notable_id')
        ->and(array_column($filters[0]->toArray(createNadotaRequest())['options'], 'value'))->toBe(['student', 'grade']);
});

it('generates no MorphTo filters when the field has no morph types', function () {
    expect(MorphTo::make('Notable', 'notable')->filterable()->filters())->toBe([]);
});
