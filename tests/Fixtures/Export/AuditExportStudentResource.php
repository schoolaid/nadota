<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Export;

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\URL;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "audit-export-student". Small Student resource for the export audit:
 *
 * - name     : on index, searchable
 * - status   : Select, exports its label
 * - website  : hidden from index but marked exportable() (AUD-015)
 * - email    : on index only for the user with id AUDITOR_ID, via a request-aware callback (AUD-016)
 */
class AuditExportStudentResource extends Resource
{
    public const AUDITOR_ID = 7;

    public string $model = Student::class;

    protected array $searchableAttributes = ['name'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->sortable(),
            Select::make('Status', 'status')->options(['draft' => 'Draft', 'enrolled' => 'Enrolled']),
            URL::make('Website', 'website')->hideFromIndex()->exportable(),
            Input::make('Email', 'email')
                ->showOnIndex(fn ($request) => $request?->user()?->getAuthIdentifier() === self::AUDITOR_ID),
        ];
    }
}
