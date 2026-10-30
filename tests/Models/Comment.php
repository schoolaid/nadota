<?php

namespace SchoolAid\Nadota\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A polymorphic child. Its table is created by the tests that use it.
 */
class Comment extends Model
{
    protected $fillable = ['body', 'commentable_type', 'commentable_id'];

    public function commentable()
    {
        return $this->morphTo();
    }
}
