<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

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
use SchoolAid\Nadota\Http\Fields\Hidden;
use SchoolAid\Nadota\Http\Fields\Html;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Json;
use SchoolAid\Nadota\Http\Fields\KeyValue;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Password;
use SchoolAid\Nadota\Http\Fields\Radio;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsTo;
use SchoolAid\Nadota\Http\Fields\Relations\BelongsToMany;
use SchoolAid\Nadota\Http\Fields\Relations\HasOne;
use SchoolAid\Nadota\Http\Fields\Relations\MorphMany;
use SchoolAid\Nadota\Http\Fields\Relations\MorphOne;
use SchoolAid\Nadota\Http\Fields\Relations\MorphToMany;
use SchoolAid\Nadota\Http\Fields\RichText;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Status;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Fields\Time;
use SchoolAid\Nadota\Http\Fields\Toggle;
use SchoolAid\Nadota\Http\Fields\URL;
use SchoolAid\Nadota\Http\Fields\VariableText;
use SchoolAid\Nadota\Http\Filters\DateFilter;
use SchoolAid\Nadota\Http\Filters\NumberFilter;
use SchoolAid\Nadota\Http\Filters\SelectFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Actions\ActivateStudents;
use SchoolAid\Nadota\Tests\Fixtures\Actions\PurgeInactiveStudents;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "student".
 *
 * The "kitchen sink" resource: one field of (almost) every scalar type plus
 * every relation the Student model has, resource-level filters, actions,
 * search over attributes and a relation, a default sort, and soft deletes
 * (autodetected from the model).
 *
 * File-based fields (File, Image, MultiImage, Signature) and the composite
 * ones (Section, DynamicField, Lookup, CustomComponent) live in
 * StudentMediaResource / StudentShowcaseResource so a plain JSON store
 * against this resource does not need multipart uploads.
 */
class StudentResource extends Resource
{
    public string $model = Student::class;

    protected array $searchableAttributes = ['name', 'email'];
    protected array $searchableRelations = ['grade.name'];
    protected array $defaultSort = ['field' => 'name', 'direction' => 'asc'];

    public const STATUSES = ['draft' => 'Draft', 'enrolled' => 'Enrolled', 'graduated' => 'Graduated'];

    public function fields(NadotaRequest $request): array
    {
        return [
            Input::make('Name', 'name')->required()->sortable()->searchable(),
            Email::make('Email', 'email')->sortable()->rules(['nullable', 'email']),
            URL::make('Website', 'website')->hideFromIndex(),
            Password::make('Password', 'password'),
            Textarea::make('Bio', 'bio')->hideFromIndex(),
            RichText::make('Rich Bio', 'rich_bio')->hideFromIndex(),
            Html::make('Html Notes', 'html_notes')->hideFromIndex(),
            Number::make('Age', 'age')->sortable()->filterable()->rules(['nullable', 'integer', 'min:0']),
            Currency::make('Balance', 'balance')->symbol('$')->sortable(),
            Date::make('Birth Date', 'birth_date')->sortable()->filterable(),
            DateTime::make('Enrolled At', 'enrolled_at')->sortable(),
            Time::make('Class Time', 'class_time')->hideFromIndex(),
            Color::make('Favorite Color', 'favorite_color')->hideFromIndex(),
            Toggle::make('Active', 'is_active')->sortable()->filterable(),
            Checkbox::make('Accepts Terms', 'accept_terms')->hideFromIndex(),
            Select::make('Status', 'status')->options(self::STATUSES)->filterable(),
            Status::make('Status Badge', 'status')
                ->addStatus('draft', 'Draft', 'gray')
                ->addStatus('enrolled', 'Enrolled', 'green')
                ->addStatus('graduated', 'Graduated', 'blue')
                ->onlyOnDetail(),
            Radio::make('Gender', 'gender')->options(['f' => 'Female', 'm' => 'Male', 'x' => 'Other'])->hideFromIndex(),
            CheckboxList::make('Interests', 'interests')->options(['art' => 'Art', 'math' => 'Math', 'music' => 'Music'])->hideFromIndex(),
            KeyValue::make('Settings', 'settings')->hideFromIndex(),
            ArrayField::make('Skills', 'skills')->strings()->hideFromIndex(),
            Json::make('Meta', 'meta')->hideFromIndex(),
            Code::make('Snippet', 'snippet')->hideFromIndex(),
            VariableText::make('Template', 'template')->hideFromIndex(),
            Hidden::make('UUID', 'uuid'),

            BelongsTo::make('Grade', 'grade', GradeResource::class)->filterable(),
            HasOne::make('Profile', 'profile', StudentProfileResource::class),
            BelongsToMany::make('Courses', 'courses', CourseResource::class),
            MorphMany::make('Notes', 'notes', NoteResource::class),
            MorphOne::make('Latest Note', 'latestNote', NoteResource::class),
            MorphToMany::make('Labels', 'labels', LabelResource::class),
            Count::make('Courses Count', 'courses'),
            Exists::make('Has Notes', 'notes'),
        ];
    }

    public function filters(NadotaRequest $request): array
    {
        return [
            SelectFilter::make('Status Filter', 'status')->options(self::STATUSES),
            NumberFilter::make('Age Range', 'age')->range(),
            DateFilter::make('Enrolled Between', 'enrolled_at')->range(),
        ];
    }

    public function actions(NadotaRequest $request): array
    {
        return [
            new ActivateStudents(),
            new PurgeInactiveStudents(),
        ];
    }
}
