<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Fields\Email;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Actions\ActivateStudents;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "scoped-student".
 *
 * Students as one tenant sees them: getQuery() hides archived students
 * (status = 'archived'), standing in for tenant / ownership scoping. Every
 * endpoint that loads records of this resource should behave as if archived
 * students did not exist.
 *
 * Its courses accept at most two attached items; its notes are not attachable.
 *
 * Also the export / global-options fixture: CSV only, two default export
 * columns, a sync export limit of 2, a bio exported although hidden from index, available in the public
 * global options endpoint with one field filter and one resource filter.
 */
class ScopedStudentResource extends Resource
{
    public string $model = Student::class;

    protected array $searchableAttributes = ['name'];
    protected array $defaultSort = ['field' => 'name', 'direction' => 'asc'];
    protected bool $availableInGlobalOptions = true;
    protected ?array $allowedExportFormats = ['csv'];
    protected ?array $defaultExportColumns = ['name', 'email'];
    protected int $syncExportLimit = 2;

    public function getQuery(NadotaRequest $request, Model $modelInstance = null): Builder
    {
        return parent::getQuery($request, $modelInstance)
            ->where(fn (Builder $query) => $query->whereNull('status')->orWhere('status', '!=', 'archived'));
    }

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->sortable()->searchable(),
            Email::make('Email', 'email')->sortable(),
            Number::make('Age', 'age')->sortable()->filterable(),
            Textarea::make('Bio', 'bio')->hideFromIndex()->exportable(),
            BelongsToMany::make('Courses', 'courses', CourseResource::class)->attachableLimit(2),
            MorphMany::make('Notes', 'notes', NoteResource::class)->attachable(false),
        ];
    }

    public function filters(NadotaRequest $request): array
    {
        return [
            SelectFilter::make('Status Filter', 'status')->options(StudentResource::STATUSES),
        ];
    }

    public function actions(NadotaRequest $request): array
    {
        return [new ActivateStudents()];
    }
}
