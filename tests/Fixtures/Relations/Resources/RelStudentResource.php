<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Relations\Resources;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Relations\ConfiguresFields;

/** Route key: "rel-student". Fields are declared per test through using(). */
class RelStudentResource extends Resource
{
    use ConfiguresFields;

    public string $model = Student::class;
}
