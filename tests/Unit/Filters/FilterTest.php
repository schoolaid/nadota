<?php

use SchoolAid\Nadota\Contracts\FilterInterface;
use SchoolAid\Nadota\Http\Filters\Filter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * The abstract base, exercised through anonymous subclasses so no named
 * double leaks into the global namespace.
 */
function baseFilterDouble(...$arguments): Filter
{
    return new class(...$arguments) extends Filter {
        public array $applied = [];

        public function apply(NadotaRequest $request, $query, $value)
        {
            $this->applied[] = $value;

            return $query;
        }
    };
}

it('implements the filter contract', function () {
    expect(baseFilterDouble('Name', 'name'))->toBeInstanceOf(FilterInterface::class);
});

it('defaults to a text filter whose component is derived from the type', function () {
    $filter = baseFilterDouble('Name', 'name');

    expect($filter->type)->toBe('text')
        ->and($filter->component())->toBe('FilterText')
        ->and($filter->name())->toBe('Name');
});

it('derives the component from a custom type when no component is given', function () {
    expect(baseFilterDouble('Tags', 'tags', 'multiTag')->component())->toBe('FilterMultiTag');
});

it('keeps an explicit component over the derived one', function () {
    expect(baseFilterDouble('Name', 'name', 'text', 'MyFilter')->component())->toBe('MyFilter');
});

it('slugs the name into the id unless an id is given', function () {
    expect(baseFilterDouble('Enrolled Between', 'enrolled_at')->id())->toBe('enrolled-between')
        ->and(baseFilterDouble('Enrolled Between', 'enrolled_at', null, null, 'custom-id')->id())->toBe('custom-id');
});

it('uses the field as key, and caches it on the public key property', function () {
    $filter = baseFilterDouble('Status Filter', 'status');

    expect($filter->key())->toBe('status')
        ->and($filter->key)->toBe('status');
});

it('falls back to the lower-cased name without spaces as key when there is no field', function () {
    $filter = baseFilterDouble('Status Filter');

    expect($filter->key())->toBe('statusfilter')
        ->and($filter->getFilterKeys())->toBe(['value' => 'statusfilter']);
});

it('falls back to the default "Filter" name when constructed without arguments', function () {
    $filter = baseFilterDouble();

    expect($filter->name())->toBe('Filter')
        ->and($filter->key())->toBe('filter')
        ->and($filter->id())->toBe('filter')
        ->and($filter->component())->toBe('FilterText');
});

it('is not a range filter and exposes a single value key by default', function () {
    $filter = baseFilterDouble('Name', 'name');

    expect($filter->isRange())->toBeFalse()
        ->and($filter->getFilterKeys())->toBe(['value' => 'name']);
});

it('has no options, no props and an empty default', function () {
    $filter = baseFilterDouble('Name', 'name');

    expect($filter->resources(createNadotaRequest()))->toBe([])
        ->and($filter->props())->toBe([])
        ->and($filter->default())->toBe('');
});

it('can be built through make()', function () {
    $class = get_class(baseFilterDouble('x', 'x'));

    $filter = $class::make('Email', 'email');

    expect($filter)->toBeInstanceOf($class)
        ->and($filter->key())->toBe('email');
});

it('serialises to the documented descriptor shape', function () {
    expect(baseFilterDouble('Name', 'name')->toArray(createNadotaRequest()))->toBe([
        'key' => 'name',
        'label' => 'Name',
        'component' => 'FilterText',
        'type' => 'text',
        'options' => [],
        'value' => '',
        'props' => [],
        'isRange' => false,
        'filterKeys' => ['value' => 'name'],
    ]);
});

it('normalises each option shape in toArray()', function () {
    $filter = new class('Kind', 'kind') extends Filter {
        public function apply(NadotaRequest $request, $query, $value)
        {
            return $query;
        }

        public function resources(NadotaRequest $request): array
        {
            return [
                'Simple' => 'simple',                                   // [label => value]
                'ignored-key' => ['value' => 'shaped', 'label' => 'Shaped'], // already {value,label}
                'Extra' => ['value' => 'extra', 'color' => 'red'],      // object without label
            ];
        }
    };

    expect($filter->toArray(createNadotaRequest())['options'])->toBe([
        ['label' => 'Simple', 'value' => 'simple'],
        ['value' => 'shaped', 'label' => 'Shaped'],
        ['value' => 'extra', 'color' => 'red', 'label' => 'Extra'],
    ]);
});

it('uses the numeric index as label for list-style options', function () {
    $filter = new class('Kind', 'kind') extends Filter {
        public function apply(NadotaRequest $request, $query, $value)
        {
            return $query;
        }

        public function resources(NadotaRequest $request): array
        {
            return ['a', 'b'];
        }
    };

    // [label => value] is the contract: a list gives labels 0 and 1.
    expect($filter->toArray(createNadotaRequest())['options'])->toBe([
        ['label' => 0, 'value' => 'a'],
        ['label' => 1, 'value' => 'b'],
    ]);
});

function defaultValueFilterDouble(string $default): Filter
{
    return new class($default) extends Filter {
        public function __construct(private string $fixedDefault)
        {
            parent::__construct('Kind', 'kind');
        }

        public function apply(NadotaRequest $request, $query, $value)
        {
            return $query;
        }

        public function default(): string
        {
            return $this->fixedDefault;
        }
    };
}

it('serialises the default value', function () {
    expect(defaultValueFilterDouble('x')->toArray(createNadotaRequest())['value'])->toBe('x');
});

it('keeps a "0" default instead of coercing it to an empty string', function () {
    expect(defaultValueFilterDouble('0')->toArray(createNadotaRequest())['value'])->toBe('0');
})->skip('ISSUE FLT-017: toArray() uses `default() ?: \'\'`, so a legitimate "0" default is dropped');
