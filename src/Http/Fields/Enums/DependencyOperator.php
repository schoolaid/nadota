<?php

namespace SchoolAid\Nadota\Http\Fields\Enums;

enum DependencyOperator: string
{
    // Comparison operators
    case EQUALS = 'equals';
    case NOT_EQUALS = 'notEquals';
    case GREATER_THAN = 'greaterThan';
    case LESS_THAN = 'lessThan';
    case GREATER_THAN_OR_EQUALS = 'greaterThanOrEquals';
    case LESS_THAN_OR_EQUALS = 'lessThanOrEquals';

    // Value presence operators
    case HAS_VALUE = 'hasValue';
    case IS_EMPTY = 'isEmpty';
    case IS_TRUTHY = 'isTruthy';
    case IS_FALSY = 'isFalsy';

    // Collection operators
    case IN = 'in';
    case NOT_IN = 'notIn';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'notContains';

    // String operators
    case STARTS_WITH = 'startsWith';
    case ENDS_WITH = 'endsWith';
    case MATCHES = 'matches'; // regex

    /**
     * Check if this operator requires a comparison value.
     */
    public function requiresValue(): bool
    {
        return match ($this) {
            self::HAS_VALUE,
            self::IS_EMPTY,
            self::IS_TRUTHY,
            self::IS_FALSY => false,
            default => true,
        };
    }

    /**
     * Check if this operator expects an array value.
     */
    public function expectsArray(): bool
    {
        return match ($this) {
            self::IN,
            self::NOT_IN => true,
            default => false,
        };
    }
}
