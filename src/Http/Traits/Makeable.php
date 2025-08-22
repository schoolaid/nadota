<?php

namespace SchoolAid\Nadota\Http\Traits;

trait Makeable
{
    public static function make(...$arguments)
    {
        return new static(...$arguments);
    }
}
