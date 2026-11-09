<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $table = 'fx_courses';
    protected $guarded = [];

    public function students()
    {
        return $this->belongsToMany(Student::class, 'fx_course_student')
            ->withPivot('score')
            ->withTimestamps();
    }
}
