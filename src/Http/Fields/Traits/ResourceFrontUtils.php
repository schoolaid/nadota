<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

trait ResourceFrontUtils
{
    public function apiUrl(): string
    {
        return config('nadota.api.prefix') . '/' . $this->getKey() . '/resource';
    }

    public function frontendUrl(): string
    {
        return config('nadota.frontend.prefix') . '/' . $this->getKey();
    }
    public function description(): ?string
    {
        return null;
    }
}
