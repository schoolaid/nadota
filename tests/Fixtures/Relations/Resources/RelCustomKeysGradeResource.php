<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Relations\Resources;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Relations\ConfiguresFields;
use SchoolAid\Nadota\Tests\Fixtures\Relations\Models\CustomKeysGrade;

/** Fields are declared per test through using(). */
class RelCustomKeysGradeResource extends Resource
{
    use ConfiguresFields;

    public string $model = CustomKeysGrade::class;
}
