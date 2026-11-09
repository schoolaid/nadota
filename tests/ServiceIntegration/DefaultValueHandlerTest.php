<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Number;
use SchoolAid\Nadota\Http\Fields\Select;
use SchoolAid\Nadota\Http\Services\Handlers\DefaultValueHandler;
use SchoolAid\Nadota\Tests\Fixtures\FixtureSchema;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Resources\LifecycleStudentResource;

/**
 * DefaultValueHandler on its own. Note that no service calls it (see ISSUE
 * HTTP-009): store/update only resolve defaults for attributes present in
 * the request.
 */
beforeEach(function () {
    FixtureSchema::create();

    $this->fields = collect([
        Input::make('Name', 'name'),
        Number::make('Age', 'age')->default(7),
        Select::make('Status', 'status')->options(['draft' => 'Draft'])->default('draft'),
    ]);
    $this->apply = fn (array $data) => (new DefaultValueHandler())->applyDefaults(
        $this->fields, $data, createNadotaRequest(), new Student(), new LifecycleStudentResource()
    );
});

it('fills the default of a field whose attribute is missing or null', function () {
    expect(($this->apply)(['name' => 'Ana', 'status' => null]))
        ->toBe(['name' => 'Ana', 'status' => 'draft', 'age' => 7]);
});

it('keeps the values that were sent', function () {
    expect(($this->apply)(['name' => 'Ana', 'age' => 12, 'status' => 'enrolled']))
        ->toBe(['name' => 'Ana', 'age' => 12, 'status' => 'enrolled']);
});

it('leaves a missing attribute without a default alone', function () {
    expect(($this->apply)(['age' => 12, 'status' => 'enrolled']))
        ->toBe(['age' => 12, 'status' => 'enrolled', 'name' => null]);
})->skip('ISSUE HTTP-009: applyDefaults() reads $validatedData[$attribute] for a missing attribute without default, an "Undefined array key" error');
