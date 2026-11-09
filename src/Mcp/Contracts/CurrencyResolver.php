<?php

namespace SchoolAid\Nadota\Mcp\Contracts;

/**
 * The currency of a money attribute whose source is `'default'`, or of a record whose currency
 * column is empty. The default implementation reads `config('nadota.mcp.default_currency')`; an
 * app with a currency per tenant or per session binds its own.
 */
interface CurrencyResolver
{
    /**
     * ISO 4217 code, or null when unknown.
     */
    public function defaultCurrency(): ?string;
}
