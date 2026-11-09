<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Relations\Models;

use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;
use SchoolAid\Nadota\Tests\Fixtures\Models\School;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

/**
 * School whose HasManyThrough uses a non-primary second local key:
 * fx_grades.level is matched against fx_students.age.
 */
class CustomKeysSchool extends School
{
    public function studentsByLevel()
    {
        return $this->hasManyThrough(Student::class, Grade::class, 'school_id', 'age', 'id', 'level');
    }
}
