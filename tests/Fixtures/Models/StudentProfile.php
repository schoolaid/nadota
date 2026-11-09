<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    protected $table = 'fx_student_profiles';
    protected $guarded = [];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
