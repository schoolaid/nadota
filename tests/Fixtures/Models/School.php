<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $table = 'fx_schools';
    protected $guarded = [];

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function students()
    {
        return $this->hasManyThrough(Student::class, Grade::class);
    }
}
