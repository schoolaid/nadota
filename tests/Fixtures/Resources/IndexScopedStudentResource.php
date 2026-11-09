<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "index-scoped-student".
 *
 * Two levels of query scoping, the way an application isolates data:
 *   getQuery()   -> only active students    (stands in for a tenant / ownership scope)
 *   queryIndex() -> only "enrolled" students (an index-listing scope)
 *
 * Used by the HTTP CRUD tests to check which routes honour which scope.
 */
class IndexScopedStudentResource extends Resource
{
    public string $model = Student::class;

    public function getQuery(NadotaRequest $request, ?Model $modelInstance = null): Builder
    {
        return parent::getQuery($request, $modelInstance)->where('is_active', true);
    }

    public function queryIndex(NadotaRequest $request, $query)
    {
        return $query->where('status', 'enrolled');
    }

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->required()->sortable(),
            Select::make('Status', 'status')->options(StudentResource::STATUSES),
            Toggle::make('Active', 'is_active'),
        ];
    }
}
