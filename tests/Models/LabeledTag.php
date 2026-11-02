<?php

namespace SchoolAid\Nadota\Tests\Models;

/**
 * A Tag whose display label is an accessor rather than a column.
 */
class LabeledTag extends Tag
{
    protected $table = 'tags';

    public function getLabelTextAttribute(): string
    {
        return "#{$this->getKey()} {$this->name}";
    }
}
