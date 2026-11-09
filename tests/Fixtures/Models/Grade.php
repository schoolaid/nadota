<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $table = 'fx_grades';
    protected $guarded = [];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function firstStudentProfile()
    {
        return $this->hasOneThrough(StudentProfile::class, Student::class);
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'notable');
    }
}
