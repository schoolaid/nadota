<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Relations\Models;

use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;
use SchoolAid\Nadota\Tests\Fixtures\Models\StudentProfile;

/**
 * Grade whose HasOneThrough uses a non-primary second local key:
 * fx_students.age is matched against fx_student_profiles.student_id.
 */
class CustomKeysGrade extends Grade
{
    public function profileByAge()
    {
        return $this->hasOneThrough(StudentProfile::class, Student::class, 'grade_id', 'student_id', 'id', 'age');
    }
}
