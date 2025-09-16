<?php

use SchoolAid\Nadota\Http\Fields\Code;
use SchoolAid\Nadota\Http\Fields\Enums\FieldType;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Models\User;

it('can create a code field', function () {
    $field = new Code('Script', 'script');

    expect($field->getName())->toBe('Script');
    expect($field->getAttribute())->toBe('script');
    expect($field->getType())->toBe(FieldType::CODE->value);
});

it('can set language', function () {
    $field = Code::make('Script', 'script')
        ->language('python');

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['language'])->toBe('python');
});

it('can set theme', function () {
    $field = Code::make('Script', 'script')
        ->theme('dark');

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['theme'])->toBe('dark');
});

it('can set show line numbers', function () {
    $field = Code::make('Script', 'script')
        ->showLineNumbers(false);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['showLineNumbers'])->toBeFalse();
});

it('can set editable', function () {
    $field = Code::make('Script', 'script')
        ->editable(false);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['editable'])->toBeFalse();
});

it('can set max height', function () {
    $field = Code::make('Script', 'script')
        ->maxHeight(500);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['maxHeight'])->toBe(500);
});

it('can set min height', function () {
    $field = Code::make('Script', 'script')
        ->minHeight(100);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['minHeight'])->toBe(100);
});

it('can set syntax highlighting', function () {
    $field = Code::make('Script', 'script')
        ->syntaxHighlighting(false);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['syntaxHighlighting'])->toBeFalse();
});

it('can set word wrap', function () {
    $field = Code::make('Script', 'script')
        ->wordWrap(true);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['wordWrap'])->toBeTrue();
});

it('can set tab size', function () {
    $field = Code::make('Script', 'script')
        ->tabSize(2);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['tabSize'])->toBe(2);
});

it('has language helper methods', function () {
    $request = NadotaRequest::create('/');

    $phpField = Code::make('Script', 'script')->php();
    expect($phpField->toArray($request)['props']['language'])->toBe('php');

    $jsField = Code::make('Script', 'script')->javascript();
    expect($jsField->toArray($request)['props']['language'])->toBe('javascript');

    $pythonField = Code::make('Script', 'script')->python();
    expect($pythonField->toArray($request)['props']['language'])->toBe('python');

    $htmlField = Code::make('Script', 'script')->html();
    expect($htmlField->toArray($request)['props']['language'])->toBe('html');

    $cssField = Code::make('Script', 'script')->css();
    expect($cssField->toArray($request)['props']['language'])->toBe('css');

    $sqlField = Code::make('Script', 'script')->sql();
    expect($sqlField->toArray($request)['props']['language'])->toBe('sql');

    $jsonField = Code::make('Script', 'script')->json();
    expect($jsonField->toArray($request)['props']['language'])->toBe('json');

    $yamlField = Code::make('Script', 'script')->yaml();
    expect($yamlField->toArray($request)['props']['language'])->toBe('yaml');

    $xmlField = Code::make('Script', 'script')->xml();
    expect($xmlField->toArray($request)['props']['language'])->toBe('xml');

    $markdownField = Code::make('Script', 'script')->markdown();
    expect($markdownField->toArray($request)['props']['language'])->toBe('markdown');

    $shellField = Code::make('Script', 'script')->shell();
    expect($shellField->toArray($request)['props']['language'])->toBe('shell');
});

it('can chain multiple settings', function () {
    $field = Code::make('Script', 'script')
        ->php()
        ->theme('dark')
        ->showLineNumbers(true)
        ->editable(false)
        ->maxHeight(600)
        ->minHeight(200)
        ->syntaxHighlighting(true)
        ->wordWrap(true)
        ->tabSize(2);

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['language'])->toBe('php');
    expect($array['props']['theme'])->toBe('dark');
    expect($array['props']['showLineNumbers'])->toBeTrue();
    expect($array['props']['editable'])->toBeFalse();
    expect($array['props']['maxHeight'])->toBe(600);
    expect($array['props']['minHeight'])->toBe(200);
    expect($array['props']['syntaxHighlighting'])->toBeTrue();
    expect($array['props']['wordWrap'])->toBeTrue();
    expect($array['props']['tabSize'])->toBe(2);
});

it('defaults to javascript language', function () {
    $field = Code::make('Script', 'script');

    $request = NadotaRequest::create('/');
    $array = $field->toArray($request);

    expect($array['props']['language'])->toBe('javascript');
});

it('resolves field value from model', function () {
    $field = Code::make('Script', 'script');

    $user = new User();
    $user->script = 'console.log("Hello World");';

    $request = NadotaRequest::create('/');
    $resolved = $field->resolve($request, $user, null);

    expect($resolved)->toBe('console.log("Hello World");');
});