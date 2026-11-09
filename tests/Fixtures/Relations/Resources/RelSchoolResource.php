<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Relations\Resources;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Relations\ConfiguresFields;

/** Route key: "rel-school". Fields are declared per test through using(). */
class RelSchoolResource extends Resource
{
    use ConfiguresFields;

    public string $model = School::class;
}
