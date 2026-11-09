<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Relations\Models;

use SchoolAid\Nadota\Tests\Fixtures\Models\Note;

/**
 * Note whose morphTo relation name ("owner") differs from its columns
 * (notable_type / notable_id).
 */
class OwnedNote extends Note
{
    public function owner()
    {
        return $this->morphTo('owner', 'notable_type', 'notable_id');
    }
}
