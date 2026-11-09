<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    protected $table = 'fx_labels';
    protected $guarded = [];

    public function students()
    {
        return $this->morphedByMany(Student::class, 'labelable', 'fx_labelables')->withTimestamps();
    }
}
