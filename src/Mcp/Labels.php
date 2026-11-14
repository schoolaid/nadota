<?php

namespace SchoolAid\Nadota\Mcp;

use Illuminate\Support\Facades\Lang;

/**
 * The text the tools hand to the agent for a resource title, a field label or an option label.
 * Nadota labels are often translation keys that the panel's frontend resolves, so the agent
 * gets the backend's translation instead: first the key under each prefix in
 * `nadota.mcp.translation_prefixes` that exists (an app whose panel keys live in one group,
 * say `global.`), then the bare key, then the label as written.
 */
class Labels
{
    public function translate(string $label): string
    {
        if ($label === '') {
            return $label;
        }

        foreach ((array) config('nadota.mcp.translation_prefixes', []) as $prefix) {
            $key = $prefix.$label;

            if (Lang::has($key)) {
                $text = __($key);

                if (is_string($text)) {
                    return $text;
                }
            }
        }

        $text = __($label);

        return is_string($text) ? $text : $label;
    }
}
