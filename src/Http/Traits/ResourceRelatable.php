<?php

namespace SchoolAid\Nadota\Http\Traits;

trait ResourceRelatable
{
    public array $with = [];
    public static string $attributeKey = 'id';
}
