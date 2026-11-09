<?php

use SchoolAid\Nadota\Http\Fields\Input;

it('field has no validation rules by default', function () {
    $field = Input::make('Name', 'name');
    
    expect($field->getRules())->toBe([]);
});

it('can add validation rules', function () {
    $field = Input::make('Email', 'email')
        ->rules(['email', 'unique:users,email']);
    
    expect($field->getRules())
        ->toContain('email')
        ->toContain('unique:users,email');
});

it('can make field required', function () {
    $field = Input::make('Name', 'name')
        ->required();
    
    expect($field->isRequired())->toBeTrue()
        ->and($field->getRules())->toContain('required');
});

it('can make field nullable', function () {
    $field = Input::make('Name', 'name')
        ->nullable();
    
    expect($field->getRules())->toContain('nullable');
});

it('can add single validation rule', function () {
    $field = Input::make('Age', 'age')
        ->rules('numeric');
    
    expect($field->getRules())->toContain('numeric');
});

it('can add rules with closure', function () {
    $field = Input::make('Name', 'name')
        ->rules(function () {
            return ['min:2', 'max:100'];
        });
    
    expect($field->getRules())
        ->toContain('min:2')
        ->toContain('max:100');
});

it('can add conditional rules', function () {
    $field = Input::make('Discount', 'discount')
        ->sometimes(function () {
            // Simulate condition where discount is required
            return ['required', 'numeric', 'min:0', 'max:100'];
        });
    
    $rules = $field->getRules();
    
    expect($rules)
        ->toContain('required')
        ->toContain('numeric')
        ->toContain('min:0')
        ->toContain('max:100');
});

it('can combine multiple rule methods', function () {
    $field = Input::make('Email', 'email')
        ->required()
        ->nullable()
        ->rules(['email', 'unique:users,email']);
    
    $rules = $field->getRules();
    
    expect($rules)
        ->toContain('required')
        ->toContain('nullable')
        ->toContain('email')
        ->toContain('unique:users,email');
});

it('includes validation rules in array representation', function () {
    $field = Input::make('Email', 'email')
        ->rules(['email', 'required']);
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['rules'])
        ->toContain('email')
        ->toContain('required');
});

it('includes required in array representation', function () {
    $field = Input::make('Name', 'name')
        ->required();
    
    $request = createNadotaRequest();
    $array = $field->toArray($request, null, null);
    
    expect($array['required'])->toBeTrue();
});

it('can use requiredIf validation', function () {
    $field = Input::make('Company', 'company')
        ->requiredIf('is_business', true);
    
    expect($field->getRules())->toContain('required_if:is_business,1');
});

it('can use requiredUnless validation', function () {
    $field = Input::make('Reason', 'reason')
        ->requiredUnless('approved', true);
    
    expect($field->getRules())->toContain('required_unless:approved,1');
});

it('removes duplicate rules', function () {
    $field = Input::make('Email', 'email')
        ->rules(['email'])
        ->rules(['email', 'unique:users,email']);
    
    $rules = $field->getRules();
    
    // Should only have one 'email' rule despite adding it twice
    $emailCount = count(array_filter($rules, fn($rule) => $rule === 'email'));
    expect($emailCount)->toBe(1);
});
it('swaps required for nullable with updateRules and back with creationRules', function () {
    $field = Input::make('Password', 'password')
        ->required()
        ->rules(['min:8'])
        ->updateRules(['nullable']);

    expect($field->getRulesFor(true))->toBe(['min:8', 'nullable'])
        ->and($field->getRulesFor(false))->toBe(['required', 'min:8'])
        ->and($field->isRequired(true))->toBeFalse()
        ->and($field->isRequired(false))->toBeTrue();

    $optional = Input::make('Code', 'code')->nullable()->creationRules(['required']);

    expect($optional->getRulesFor(false))->toBe(['required'])
        ->and($optional->getRulesFor(true))->toBe(['nullable']);
});

it('sends context rules to the form when the request carries an action', function () {
    $field = Input::make('Password', 'password')->required()->updateRules(['nullable']);

    expect($field->toArray(createNadotaRequest(['action' => 'edit']))['rules'])->toBe(['nullable'])
        ->and($field->toArray(createNadotaRequest(['action' => 'edit']))['required'])->toBeFalse()
        ->and($field->toArray(createNadotaRequest(['action' => 'create']))['rules'])->toBe(['required'])
        ->and($field->toArray(createNadotaRequest())['rules'])->toBe(['required']);
});

it('keeps closures and rule objects when deduplicating', function () {
    $closure = fn ($attribute, $value, $fail) => null;
    $object = new \Illuminate\Validation\Rules\In(['a', 'b']);

    $rules = Input::make('Code', 'code')->rules(['string', 'string'])->rules([$closure, $object])->getRules();

    expect($rules)->toHaveCount(3)
        ->and($rules[0])->toBe('string')
        ->and($rules[1])->toBe($closure)
        ->and($rules[2])->toBe($object);
});

it('evaluates sometimes() callbacks every time, without arguments', function () {
    // Not Laravel's "sometimes": the callback is a lazy rule list, it does not
    // see the input. A falsy return adds nothing.
    $calls = 0;
    $field = Input::make('Discount', 'discount')->sometimes(function (...$args) use (&$calls) {
        $calls++;

        return $args === [] ? null : ['never'];
    });

    expect($field->getRules())->toBe([])
        ->and($field->getRules())->toBe([])
        ->and($calls)->toBe(2);
});

it('keys custom messages by attribute and keeps dotted keys as they are', function () {
    $messages = Input::make('Photos', 'photos')
        ->validationMessages(['required' => 'Add at least one photo'])
        ->validationMessages(['photos.*.image' => 'Only images'])
        ->getValidationMessages();

    expect($messages)->toBe([
        'photos.required' => 'Add at least one photo',
        'photos.*.image' => 'Only images',
    ]);
});

it('reports required when the rule was added through rules()', function () {
    $field = Input::make('Name', 'name')->rules(['required', 'string']);

    expect($field->isRequired())->toBeTrue()
        ->and($field->toArray(createNadotaRequest())['required'])->toBeTrue();
})->skip('ISSUE TRT-008: isRequired() without context only reads the required() flag, not the rules');

it('makes requiredIf(field, true) fire for a JSON boolean true', function () {
    $rules = ['company' => Input::make('Company', 'company')->requiredIf('is_business', true)->getRules()];

    $validator = \Illuminate\Support\Facades\Validator::make(['is_business' => true], $rules);

    expect($validator->fails())->toBeTrue();
})->skip('ISSUE TRT-007: requiredIf() renders true as "1", Laravel compares a boolean strictly and never requires');

it('makes requiredIf(field, false) fire for a JSON boolean false', function () {
    $rules = ['reason' => Input::make('Reason', 'reason')->requiredIf('approved', false)->getRules()];

    $validator = \Illuminate\Support\Facades\Validator::make(['approved' => false], $rules);

    expect($validator->fails())->toBeTrue();
})->skip('ISSUE TRT-007: requiredIf() renders false as an empty string ("required_if:approved,")');

it('treats a string rule that names a PHP function as a rule', function (string $rule) {
    expect(Input::make('Born', 'born')->rules($rule)->getRules())->toBe([$rule]);
})->with(['date', 'file'])
    ->skip('ISSUE TRT-004: rules() calls is_callable() on strings, date()/file() are invoked');
