<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Resources;

use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * A resource whose fields (and protected instance knobs) are injected, for
 * unit tests of the resource and field traits that need "a resource with
 * exactly these fields" without declaring a class per test.
 *
 *     AdHocResource::withFields([Input::make('Name', 'name')], ['perPage' => 5]);
 *
 * The model defaults to the fixture Student (fx_students); call
 * FixtureSchema::create() before querying. Static properties such as
 * $attributeKey are deliberately not configurable here: setting them would
 * leak into every other test. Declare an anonymous subclass instead.
 *
 * Not registered with ResourceManager; attach it to a request with
 * $request->setResource(...) when a service needs it.
 */
class AdHocResource extends Resource
{
    public string $model = Student::class;

    /**
     * @param array<int, mixed> $fieldList Fields (or Sections) returned by fields().
     * @param array<string, mixed> $config Protected/public instance properties to override.
     */
    public function __construct(private array $fieldList = [], array $config = [], ?string $model = null)
    {
        parent::__construct();

        if ($model !== null) {
            $this->model = $model;
        }

        foreach ($config as $property => $value) {
            $this->{$property} = $value;
        }
    }

    public static function withFields(array $fields, array $config = [], ?string $model = null): static
    {
        return new static($fields, $config, $model);
    }

    public function fields(NadotaRequest $request): array
    {
        return $this->fieldList;
    }
}
