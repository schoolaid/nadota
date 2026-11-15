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

    /**
     * A morphMany, declared in HasManyAttachTestResource as a HasMany field (like `attachments` in
     * host apps). The `notes` table is created by the test that uses it.
     */
    public function notes()
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /**
     * The same morphMany, for a MorphMany field (HasManyAttachTestResource).
     */
    public function morphNotes()
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /**
     * A morphMany with NOT NULL morph columns. The `comments` table is created by the test.
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * The same HasMany as relatedModels, for a field without attachable().
     */
    public function plainRelated()
    {
        return $this->hasMany(RelatedModel::class);
    }

    protected static function newFactory()
    {
        return TestModelFactory::new();
    }
}