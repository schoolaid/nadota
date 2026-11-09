<?php

namespace SchoolAid\Nadota\Mcp;

use SchoolAid\Nadota\Mcp\Contracts\CurrencyResolver;

class ConfigCurrencyResolver implements CurrencyResolver
{
    public function defaultCurrency(): ?string
    {
        $currency = config('nadota.mcp.default_currency');

        return is_string($currency) && $currency !== '' ? $currency : null;
    }
}
