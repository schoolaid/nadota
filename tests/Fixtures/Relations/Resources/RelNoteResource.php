<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Relations\Resources;

use SchoolAid\Nadota\Resource;
use SchoolAid\Nadota\Tests\Fixtures\Models\Note;
use SchoolAid\Nadota\Tests\Fixtures\Relations\ConfiguresFields;

/** Route key: "rel-note". Fields are declared per test through using(). */
class RelNoteResource extends Resource
{
    use ConfiguresFields;

    public string $model = Note::class;
}
