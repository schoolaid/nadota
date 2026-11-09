<?php

use SchoolAid\Nadota\Http\Actions\DestructiveAction;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Fixtures\Actions\PurgeInactiveStudents;

it('presets the destructive flag, the Delete button and a confirmation text', function () {
    $action = PurgeInactiveStudents::make();

    expect($action)->toBeInstanceOf(DestructiveAction::class)
        ->and($action->isDestructive())->toBeTrue()
        ->and($action->confirmButtonText())->toBe('Delete')
        ->and($action->confirmText())->toBe('Are you sure you want to run this action?')
        ->and($action->cancelButtonText())->toBe('Cancel');
});

it('serializes the presets and the subclass overrides', function () {
    // PurgeInactiveStudents overrides $standalone = true and $showOnDetail = false.
    expect(PurgeInactiveStudents::make()->toArray(new NadotaRequest()))->toMatchArray([
        'key' => 'purge-inactive-students',
        'name' => 'Purge Inactive Students',
        'destructive' => true,
        'standalone' => true,
        'showOnIndex' => true,
        'showOnDetail' => false,
        'confirmText' => 'Are you sure you want to run this action?',
        'confirmButtonText' => 'Delete',
    ]);
});

it('lets the presets be changed fluently', function () {
    $action = PurgeInactiveStudents::make()
        ->destructive(false)
        ->withConfirmation('Purge?')
        ->setConfirmButtonText('Purge');

    expect($action->isDestructive())->toBeFalse()
        ->and($action->confirmText())->toBe('Purge?')
        ->and($action->confirmButtonText())->toBe('Purge');
});
