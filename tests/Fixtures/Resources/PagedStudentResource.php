<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\Email;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "paged-student".
 *
 * Every index/info knob set to a non-default value:
 *   perPage 5, allowedPerPage [5, 15], ids left out of responses, eager loads
 *   "profile" on index, no default sort (falls back to created_at desc),
 *   custom title/description/components/card/selection/polling, search key
 *   "q" over name plus a custom applySearch() matching the age.
 *
 * When an ArrayObject is bound as "paged-student.fields-built", every call to
 * fields() appends to it, so a test can count how often the field list is built.
 */
class PagedStudentResource extends Resource
{
    public string $model = Student::class;

    protected ?string $title = 'Pupils';
    protected int $perPage = 5;
    protected array $allowedPerPage = [5, 15];
    protected bool $includeIdInResponse = false;
    protected array $withOnIndex = ['profile'];
    protected string $searchKey = 'q';
    protected array $searchableAttributes = ['name'];
    protected ?int $pollingInterval = 30;
    protected bool $showRowCheckbox = true;
    protected bool $showSelectAll = true;
    protected ?string $detailCardWidth = 'lg';
    protected bool $mainCardCollapsible = false;
    protected bool $mainCardDefaultCollapsed = false;
    protected ?string $mainCardTitle = 'Pupil list';
    protected string $indexComponent = 'PupilIndex';
    protected string $showComponent = 'PupilShow';

    public function description(): ?string
    {
        return 'Students, five at a time';
    }

    public function fields(NadotaRequest $request): array
    {
        if (app()->bound('paged-student.fields-built')) {
            app('paged-student.fields-built')->append(true);
        }

        return [
            Input::make('Name', 'name')->required()->sortable(),
            Number::make('Age', 'age')->sortable(),
            Email::make('Email', 'email'),
        ];
    }

    public function applySearch($query, string $search): void
    {
        if (is_numeric($search)) {
            $query->orWhere('age', (int) $search);
        }
    }
}
