<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Fields\ArrayField;
use SchoolAid\Nadota\Http\Fields\Checkbox;
use SchoolAid\Nadota\Http\Fields\CustomComponent;
use SchoolAid\Nadota\Http\Fields\DynamicField;
use SchoolAid\Nadota\Http\Fields\Hidden;
use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\KeyValue;
use SchoolAid\Nadota\Http\Fields\Lookup;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Fields\Signature;
use SchoolAid\Nadota\Http\Fields\Textarea;
use SchoolAid\Nadota\Http\Fields\URL;
use SchoolAid\Nadota\Http\Fields\VariableText;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * Route key: "student-showcase".
 *
 * The composite / layout fields on the Student model:
 *
 *   Section "Identity"   name (required), status (Select), age (Number, default 18),
 *                        uuid (Hidden), grade_filter (Lookup -> GradeResource, never persisted)
 *   Section "Answers"    snippet (DynamicField based on status:
 *                                 enrolled -> Checkbox yes/no, draft -> Number, otherwise Textarea),
 *                        template (VariableText requiring {{name}}),
 *                        signature (Signature kept as a base64 data URL)
 *   Section "Preferences" settings (KeyValue: theme default "light", locale readonly, token hidden,
 *                                   no other keys allowed),
 *                        skills (ArrayField of unique e-mails, at most 3)
 *   Section "Admin"      website (URL), hidden from the creation form
 *   (loose)              CustomComponent "Progress Chart" (detail only, data from the model)
 */
class StudentShowcaseResource extends Resource
{
    public string $model = Student::class;

    public const CHART_COMPONENT = 'StudentProgressChart';

    public function fields(NadotaRequest $request): array
    {
        return [
            Section::make('Identity', [
                Input::make('Name', 'name')->required(),
                Select::make('Status', 'status')->options(StudentResource::STATUSES),
                Number::make('Age', 'age')->default(18)->nullable(),
                Hidden::make('UUID', 'uuid'),
                Lookup::make('Grade Filter', 'grade_filter')->resource(GradeResource::class),
            ])->icon('user')->description('Who the student is'),

            Section::make('Answers', [
                DynamicField::make('Answer', 'snippet')
                    ->basedOn('status')
                    ->types([
                        'enrolled' => Checkbox::make('Answer', 'snippet')->trueValue('yes')->falseValue('no'),
                        'draft' => Number::make('Answer', 'snippet'),
                    ])
                    ->defaultType(Textarea::make('Answer', 'snippet')),
                VariableText::make('Template', 'template')
                    ->requiredVariables(['name'])
                    ->optionalVariables(['grade'])
                    ->mustacheStyle(),
                Signature::make('Consent', 'signature'),
            ])->collapsible(),

            Section::make('Preferences', [
                KeyValue::make('Settings', 'settings')->schema([
                    'theme' => ['label' => 'Theme', 'default' => 'light'],
                    'locale' => ['label' => 'Locale', 'readonly' => true],
                    'token' => ['label' => 'Token', 'hidden' => true],
                ]),
                ArrayField::make('Contact Emails', 'skills')->emails()->unique()->max(3),
            ])->collapsed(),

            Section::make('Admin', [
                URL::make('Website', 'website')->nullable(),
            ])->hideFromCreation(),

            CustomComponent::make('Progress Chart', self::CHART_COMPONENT)
                ->withProps(['color' => 'green'])
                ->withData(fn (Student $student) => ['age' => $student->age])
                ->below(),
        ];
    }
}
