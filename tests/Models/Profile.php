<?php

namespace Said\Nadota\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Said\Nadota\Tests\Database\Factories\ProfileFactory;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'bio',
        'test_model_id',
    ];

    public function testModel()
    {
        return $this->belongsTo(TestModel::class);
    }

    protected static function newFactory()
    {
        return ProfileFactory::new();
    }
}