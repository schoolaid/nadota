<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Relations\Resources;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Course;
use SchoolAid\Nadota\Tests\Fixtures\Relations\ConfiguresFields;

/** Route key: "rel-course". Fields and the getQuery() scope are declared per test. */
class RelCourseResource extends Resource
{
    use ConfiguresFields;

    public string $model = Course::class;
}
