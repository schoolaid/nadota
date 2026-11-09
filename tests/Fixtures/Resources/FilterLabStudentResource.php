<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Checkbox;
use SchoolAid\Nadota\Http\Fields\CheckboxList;
use SchoolAid\Nadota\Http\Fields\Count;
use SchoolAid\Nadota\Http\Fields\DateTime;
use SchoolAid\Nadota\Http\Fields\Email;
use SchoolAid\Nadota\Http\Fields\Exists;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Radio;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasOne;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Time;
use SchoolAid\Nadota\Http\Filters\BooleanFilter;
use SchoolAid\Nadota\Http\Filters\DefaultFilter;
use SchoolAid\Nadota\Http\Filters\DynamicSelectFilter;
use SchoolAid\Nadota\Http\Filters\ExistsFilter;
use SchoolAid\Nadota\Http\Filters\RangeFilter;
use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "filter-lab-student".
 *
 * Filter-focused twin of StudentResource (same model and table): every
 * field-generated filter type that StudentResource does not declare, plus
 * one resource filter of each remaining Filter class. Keys never collide
 * here — StudentResource keeps the collision cases (age, status).
 *
 * Used by tests/Feature/Http/Filters; register it with
 * $this->bootNadotaApi([...$this->fixtureResources, FilterLabStudentResource::class]).
 */
class FilterLabStudentResource extends Resource
{
    public string $model = Student::class;

    protected array $searchableAttributes = ['name'];
    protected array $defaultSort = ['field' => 'name', 'direction' => 'asc'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->sortable(),
            Email::make('Email', 'email')->filterable(),                              // DefaultFilter
            Input::make('Website', 'website')->hideFromIndex()->filterable(),         // listed, hidden from index
            Number::make('Age', 'age')->sortable()->filterableRange(),                // NumberFilter range
            DateTime::make('Enrolled At', 'enrolled_at')->filterableRange(),          // DateFilter range (datetime)
            Time::make('Class Time', 'class_time')->filterable(),                     // DateFilter on a time column
            Checkbox::make('Accepts Terms', 'accept_terms')->filterable(),            // BooleanFilter
            Radio::make('Gender', 'gender')->options(['f' => 'Female', 'm' => 'Male'])->filterable(),
            CheckboxList::make('Interests', 'interests')->options(['art' => 'Art', 'math' => 'Math'])->filterable(),
            BelongsTo::make('Grade', 'grade', GradeResource::class)->filterable(),
            HasOne::make('Profile', 'profile', StudentProfileResource::class)->filterable()->showOnIndex(),
            BelongsToMany::make('Courses', 'courses', CourseResource::class)->filterable(),   // hidden from index by default
            MorphMany::make('Notes', 'notes', NoteResource::class)->filterable(),              // hidden from index by default
            Exists::make('Has Notes', 'notes')->filterable(),                         // ExistsFilter
            Count::make('Courses Count', 'courses')->filterable(),                    // NumberFilter on an aggregate alias
        ];
    }

    public function filters(NadotaRequest $request): array
    {
        return [
            BooleanFilter::make('Is Active', 'is_active'),
            SelectFilter::make('Status', 'status')->options(['Draft' => 'draft', 'Enrolled' => 'enrolled', 'Graduated' => 'graduated']),
            RangeFilter::make('Balance', 'balance'),
            DynamicSelectFilter::make('School', 'school_id')->relation('grade.school'),
            new ExistsFilter('Has Pinned Notes', 'pinned_notes_exists', 'notes', fn ($q) => $q->where('pinned', true)),
            DefaultFilter::make('Favorite Color', 'favorite_color'),
        ];
    }
}
