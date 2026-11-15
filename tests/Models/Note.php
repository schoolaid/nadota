<?php

namespace SchoolAid\Nadota\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Polymorphic child (`notable_type` / `notable_id`). Its table is created by the test that uses it.
 */
class Note extends Model
{
    protected $fillable = ['title', 'notable_type', 'notable_id'];
}
