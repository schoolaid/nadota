<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Relations\Resources;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Relations\ConfiguresFields;

/** Route key: "rel-grade". Fields are declared per test through using(). */
class RelGradeResource extends Resource
{
    use ConfiguresFields;

    public string $model = Grade::class;
}
