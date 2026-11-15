<?php

namespace SchoolAid\Nadota\Http\Fields;

use SchoolAid\Nadota\Http\Fields\Enums\FieldType;

class URL extends Field
{
    public function __construct(string $name, string $attribute)
    {
        parent::__construct($name, $attribute, FieldType::URL->value, static::safeConfig('nadota.fields.url.component', 'FieldUrl'));
    }

    /**
     * The field's rules plus `url`, last.
     *
     * After `required`: Laravel skips the rules that follow a failed `required`, so an empty value
     * reports only that it is required (with `url` first it also said it was not a valid URL). Not
     * added when the app declares its own (`url:http,https`). A URL that is not required gets
     * `nullable`, otherwise `url` rejects the empty value and the field could never be cleared.
     */
    public function getRules(): array
    {
        $rules = parent::getRules();

        $strings = array_filter($rules, 'is_string');

        if (!in_array('required', $strings, true) && !in_array('nullable', $strings, true)) {
            array_unshift($rules, 'nullable');
        }

        $hasUrlRule = (bool) array_filter($strings, fn (string $rule) => $rule === 'url' || str_starts_with($rule, 'url:'));

        return $hasUrlRule ? $rules : [...$rules, 'url'];
    }

    /**
     * The context rules (creationRules()/updateRules()) go after the base ones: `url` is moved back
     * to the end so that it also follows a `required` that only applies on store or update.
     */
    public function getRulesFor(bool $isUpdate): array
    {
        $rules = parent::getRulesFor($isUpdate);

        if (!in_array('url', $rules, true)) {
            return $rules;
        }

        return [...array_values(array_filter($rules, fn ($rule) => $rule !== 'url')), 'url'];
    }
}
