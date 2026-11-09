<?php

use SchoolAid\Nadota\Http\Fields\Input;
use SchoolAid\Nadota\Http\Fields\Section;
use SchoolAid\Nadota\Http\Traits\Makeable;

it('builds an instance with the given constructor arguments', function () {
    $field = Input::make('Name', 'name');

    expect($field)->toBeInstanceOf(Input::class)
        ->and($field->getLabel())->toBe('Name')
        ->and($field->getAttribute())->toBe('name');
});

it('instantiates the class it is called on (late static binding)', function () {
    $child = new class('x', 'y') extends Input {};

    expect($child::make('Name', 'name'))->toBeInstanceOf($child::class);
});

it('forwards named arguments', function () {
    $section = Section::make(title: 'Contact', fields: [Input::make('Email', 'email')]);

    expect($section->getTitle())->toBe('Contact')
        ->and($section->getFields())->toHaveCount(1);
});

it('returns a new instance on every call', function () {
    expect(Input::make('Name', 'name'))->not->toBe(Input::make('Name', 'name'));
});

it('lets the constructor reject missing arguments', function () {
    expect(fn () => Input::make('Name'))->toThrow(ArgumentCountError::class);
});

it('works on any class', function () {
    $host = new class('a', 'b') {
        use Makeable;

        public array $args;

        public function __construct(...$args)
        {
            $this->args = $args;
        }
    };

    expect($host::make(1, 2, 3)->args)->toBe([1, 2, 3]);
});
