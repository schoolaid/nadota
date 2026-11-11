<?php

namespace SchoolAid\Nadota\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftParent extends Model
{
    use SoftDeletes;

    protected $guarded = [];
}
