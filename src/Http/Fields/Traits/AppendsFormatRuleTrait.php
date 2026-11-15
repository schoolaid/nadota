<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

/**
 * For fields that add a format rule of their own (`url`, `email`, Time's `date_format`): the rule
 * goes last, after the field's rules, and only if the field does not declare one with the same name
 * already (`url:http,https`, `email:rfc,dns`, `date_format:H:i`).
 *
 * After `required`: Laravel skips the rules that follow a failed `required`, so an empty value
 * reports only that it is required (with the format rule first it also said it was not valid). A
 * field that is not required gets `nullable`, otherwise the format rule rejects the empty value and
 * the field could never be cleared.
 *
 * The using class sets `protected string $formatRule`.
 */
trait AppendsFormatRuleTrait
{
    public function getRules(): array
    {
        $rules = parent::getRules();

        $strings = array_filter($rules, 'is_string');

        if (!in_array('required', $strings, true) && !in_array('nullable', $strings, true)) {
            array_unshift($rules, 'nullable');
        }

        $name = explode(':', $this->formatRule, 2)[0];
        $declared = (bool) array_filter($strings, fn (string $rule) => $rule === $name || str_starts_with($rule, $name . ':'));

        return $declared ? $rules : [...$rules, $this->formatRule];
    }

    /**
     * The context rules (creationRules()/updateRules()) go after the base ones: the format rule is
     * moved back to the end so that it also follows a `required` that only applies on store or update.
     */
    public function getRulesFor(bool $isUpdate): array
    {
        $rules = parent::getRulesFor($isUpdate);

        if (!in_array($this->formatRule, $rules, true)) {
            return $rules;
        }

        return [...array_values(array_filter($rules, fn ($rule) => $rule !== $this->formatRule)), $this->formatRule];
    }
}
