<?php

namespace SchoolAid\Nadota\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class SoftChild extends Model
{
    protected $guarded = [];

    public function softParent()
    {
        return $this->belongsTo(SoftParent::class);
    }

    public function testModel()
    {
        return $this->belongsTo(TestModel::class);
    }
}
