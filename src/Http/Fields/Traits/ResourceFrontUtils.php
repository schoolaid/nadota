<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

trait ResourceFrontUtils
{
    public static function apiUrl(): string
    {
        return config('nadota.api.prefix') . '/' . static::getKey() . '/resource';
    }

    public static function frontendUrl(): string
    {
        return config('nadota.frontend.prefix') . '/' . static::getKey();
    }

    public function description(): ?string
    {
        return null;
    }
}
