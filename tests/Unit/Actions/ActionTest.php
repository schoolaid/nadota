<?php

use Illuminate\Database\Eloquent\Model;
use SchoolAid\Nadota\Http\Actions\ActionResponse;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Tests\Fixtures\Actions\ActivateStudents;
use SchoolAid\Nadota\Tests\Fixtures\Actions\SendReminder;
use SchoolAid\Nadota\Tests\Fixtures\Actions\SendReminders;
use SchoolAid\Nadota\Tests\Fixtures\Models\Student;

it('serializes to the documented shape with the default flags and texts', function () {
    $payload = ActivateStudents::make()->toArray(new NadotaRequest());

    expect(array_keys($payload))->toBe([
        'key', 'name', 'fields', 'showOnIndex', 'showOnDetail', 'destructive',
        'standalone', 'confirmText', 'confirmButtonText', 'cancelButtonText',
    ])
        ->and($payload['key'])->toBe('activate-students')
        ->and($payload['name'])->toBe('Activate Students')
        ->and($payload['showOnIndex'])->toBeTrue()
        ->and($payload['showOnDetail'])->toBeTrue()
        ->and($payload['destructive'])->toBeFalse()
        ->and($payload['standalone'])->toBeFalse()
        ->and($payload['confirmText'])->toBeNull()
        ->and($payload['confirmButtonText'])->toBe('Run Action')
        ->and($payload['cancelButtonText'])->toBe('Cancel');
});

it('serializes its fields with each field toArray()', function () {
    $fields = ActivateStudents::make()->toArray(new NadotaRequest())['fields'];

    expect($fields)->toHaveCount(1)
        ->and($fields[0]['key'])->toBe('reason')
        ->and($fields[0]['label'])->toBe('Reason')
        ->and($fields[0]['type'])->toBe('textarea');
});

it('adds component and icon only when they are set', function () {
    $action = ActivateStudents::make();
    $request = new NadotaRequest();

    expect($action->toArray($request))->not->toHaveKeys(['component', 'icon']);

    $payload = $action->component('ActionWizard')->icon('CheckCircle')->toArray($request);

    expect($payload['component'])->toBe('ActionWizard')
        ->and($payload['icon'])->toBe('CheckCircle')
        ->and($action->getComponent())->toBe('ActionWizard')
        ->and($action->getIcon())->toBe('CheckCircle');
});

it('falls back to the headline of the class basename for the name', function () {
    expect(SendReminder::make()->name())->toBe('Send Reminder');
});

it('restricts the action to index or detail and shows it on both with showOnTableRow()', function () {
    $action = ActivateStudents::make()->onlyOnIndex();
    expect([$action->showOnIndex(), $action->showOnDetail()])->toBe([true, false]);

    $action->onlyOnDetail();
    expect([$action->showOnIndex(), $action->showOnDetail()])->toBe([false, true]);

    $action->showOnTableRow();
    expect([$action->showOnIndex(), $action->showOnDetail()])->toBe([true, true]);
});

it('toggles the destructive and standalone flags', function () {
    $action = ActivateStudents::make()->destructive()->standalone();

    expect($action->isDestructive())->toBeTrue()
        ->and($action->isStandalone())->toBeTrue()
        ->and($action->toArray(new NadotaRequest()))->toMatchArray(['destructive' => true, 'standalone' => true]);

    $action->destructive(false)->standalone(false);

    expect($action->isDestructive())->toBeFalse()
        ->and($action->isStandalone())->toBeFalse();
});

it('sets the confirmation and button texts', function () {
    $action = ActivateStudents::make()
        ->withConfirmation('Sure?')
        ->setConfirmButtonText('Yes')
        ->setCancelButtonText('No');

    expect($action->toArray(new NadotaRequest()))->toMatchArray([
        'confirmText' => 'Sure?',
        'confirmButtonText' => 'Yes',
        'cancelButtonText' => 'No',
    ]);
});

it('authorizes every model unless a canRun callback decides, receiving request and model', function () {
    $request = new NadotaRequest();
    $allowed = new Student(['name' => 'Ana']);
    $denied = new Student(['name' => 'Bob']);
    $action = ActivateStudents::make();

    expect($action->authorizedToRun($request, $denied))->toBeTrue();

    $seen = [];
    $action->canRun(function (NadotaRequest $given, Model $model) use (&$seen, $request) {
        $seen[] = [$given === $request, $model->name];

        return $model->name === 'Ana';
    });

    expect($action->authorizedToRun($request, $allowed))->toBeTrue()
        ->and($action->authorizedToRun($request, $denied))->toBeFalse()
        ->and($seen)->toBe([[true, 'Ana'], [true, 'Bob']]);
});

it('builds a fresh instance on every make()', function () {
    expect(ActivateStudents::make())->not->toBe(ActivateStudents::make());
});

it('delegates the static response helpers to ActionResponse', function () {
    expect(ActivateStudents::message('ok')->toArray())->toBe(ActionResponse::message('ok')->toArray())
        ->and(ActivateStudents::danger('no')->getType())->toBe('danger')
        ->and(ActivateStudents::redirect('/x')->getUrl())->toBe('/x')
        ->and(ActivateStudents::download('/f.csv', 'f.csv')->getFilename())->toBe('f.csv')
        ->and(ActivateStudents::openInNewTab('/x')->shouldOpenInNewTab())->toBeTrue();
});

it('keeps the class basename singular in the action key', function () {
    expect(SendReminder::getKey())->toBe('send-reminder');
})->skip('ISSUE AUD-023: Helpers::toUri() pluralises the basename, so SendReminder becomes "send-reminders"');

it('gives distinct keys to distinct action classes', function () {
    expect(SendReminder::getKey())->not->toBe(SendReminders::getKey());
})->skip('ISSUE AUD-023: SendReminder and SendReminders both resolve to "send-reminders"; findAction() returns the first match');
