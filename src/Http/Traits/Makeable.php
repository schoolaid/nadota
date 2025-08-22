<?php

namespace Said\Nadota\Http\Traits;

trait Makeable
{
    public static function make(...$arguments)
    {
        return new static(...$arguments);
    }
}
