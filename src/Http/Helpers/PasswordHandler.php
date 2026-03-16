<?php

namespace SchoolAid\Nadota\Http\Helpers;

class PasswordHandler
{
    /**
     * Hash the given value using SHA256 base64.
     *
     * @param string $value
     * @return string
     */
    public static function make(string $value): string
    {
        return base64_encode(hash('sha256', $value, true));
    }
}
