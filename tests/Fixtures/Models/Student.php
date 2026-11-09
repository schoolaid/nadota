<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $table = 'fx_students';
    protected $guarded = [];
    protected $hidden = ['password'];

    protected $casts = [
        'is_active' => 'boolean',
        'accept_terms' => 'boolean',
        'birth_date' => 'date',
        'enrolled_at' => 'datetime',
        'balance' => 'decimal:2',
        'interests' => 'array',
        'settings' => 'array',
        'skills' => 'array',
        'meta' => 'array',
        'photos' => 'array',
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function profile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'fx_course_student')
            ->withPivot('score')
            ->withTimestamps();
    }

    public function notes()
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function latestNote()
    {
        return $this->morphOne(Note::class, 'notable')->latestOfMany();
    }

    public function labels()
    {
        return $this->morphToMany(Label::class, 'labelable', 'fx_labelables')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
