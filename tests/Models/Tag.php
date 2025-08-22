<?php

namespace Said\Nadota\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Said\Nadota\Tests\Database\Factories\TagFactory;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function testModels()
    {
        return $this->morphedByMany(TestModel::class, 'taggable');
    }

    protected static function newFactory()
    {
        return TagFactory::new();
    }
}