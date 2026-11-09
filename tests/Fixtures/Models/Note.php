<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $table = 'fx_notes';
    protected $guarded = [];

    protected $casts = ['pinned' => 'boolean'];

    public function notable()
    {
        return $this->morphTo();
    }
}
