<?php

namespace SchoolAid\Nadota\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Tests\Database\Factories\TestModelFactory;

class TestModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email', 
        'description',
        'age',
        'is_active',
        'is_published',
        'published_at',
        'metadata',
        'status',
        'features',
        'tags',
        'accept_terms',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array',
        'features' => 'array',
        'tags' => 'array',
        'accept_terms' => 'boolean',
    ];

    public function relatedModels()
    {
        return $this->hasMany(RelatedModel::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function simpleTags()
    {
        return $this->belongsToMany(Tag::class, 'test_model_tag');
    }

    protected static function newFactory()
    {
        return TestModelFactory::new();
    }
}