<?php

namespace SchoolAid\Nadota\Http\Helpers;

use Illuminate\Support\Str;

class Helpers
{
    public static function toUri(string $class): string
    {
        $uri = Str::plural(Str::kebab(class_basename($class)));
        return str_replace('-resources', '', $uri);
    }
    public static function slug(string $text): string
    {
        return Str::slug($text);
    }

    public static function make(...$arguments): static
    {
        return new static(...$arguments);
    }
}
