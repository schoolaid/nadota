<?php

use SchoolAid\Nadota\Http\Filters\ExistsFilter;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Models\TestModel;

beforeEach(function () {
    $this->request = Mockery::mock(NadotaRequest::class);
});

afterEach(function () {
    Mockery::close();
});

it('can be instantiated', function () {
    $filter = new ExistsFilter('Has Comments', 'comments_exists', 'comments');

    expect($filter)->toBeInstanceOf(ExistsFilter::class)
        ->and($filter->name())->toBe('Has Comments')
        ->and($filter->key())->toBe('comments_exists')
        ->and($filter->getRelation())->toBe('comments');
});

it('can be instantiated with constraint', function () {
    $constraint = fn($q) => $q->where('active', true);
    $filter = new ExistsFilter('Has Active Comments', 'active_comments_exists', 'comments', $constraint);

    expect($filter)->toBeInstanceOf(ExistsFilter::class)
        ->and($filter->getRelation())->toBe('comments')
        ->and($filter->getConstraint())->toBe($constraint);
});

it('has correct component for frontend', function () {
    $filter = new ExistsFilter('Has Comments', 'comments_exists', 'comments');

    expect($filter->component())->toBe('FilterBoolean');
});
