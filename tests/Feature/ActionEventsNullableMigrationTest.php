<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `alter_action_events_user_id_nullable` only means to relax nullability. But
 * Laravel's `->change()` requires every attribute to be restated, so the
 * migration also restates the type as `unsignedBigInteger` — silently reverting
 * applications that widened `actionable_id` / `target_id` to strings in order to
 * audit resources with UUID primary keys.
 *
 * It must relax nullability without ever reshaping a column's type.
 */
beforeEach(function () {
    Schema::dropIfExists('action_events');
});

it('makes non-nullable integer ids nullable', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('actionable_id');
        $table->unsignedBigInteger('target_id');
    });

    (require __DIR__ . '/../../database/migrations/2025_12_15_000001_alter_action_events_user_id_nullable.php')->up();

    $columns = collect(Schema::getColumns('action_events'))->keyBy('name');

    expect($columns['user_id']['nullable'])->toBeTrue()
        ->and($columns['actionable_id']['nullable'])->toBeTrue()
        ->and($columns['target_id']['nullable'])->toBeTrue();
});

it('leaves application-customised string id columns untouched', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('actionable_id', 36)->nullable();
        $table->string('target_id', 36)->nullable();
    });

    (require __DIR__ . '/../../database/migrations/2025_12_15_000001_alter_action_events_user_id_nullable.php')->up();

    expect(Schema::getColumnType('action_events', 'actionable_id'))->toBe('varchar')
        ->and(Schema::getColumnType('action_events', 'target_id'))->toBe('varchar');
});

it('does not widen a non-nullable string id column to an integer', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
        $table->string('actionable_id', 36);
    });

    (require __DIR__ . '/../../database/migrations/2025_12_15_000001_alter_action_events_user_id_nullable.php')->up();

    expect(Schema::getColumnType('action_events', 'actionable_id'))->toBe('varchar');
});

it('tolerates a table that does not carry every column', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
    });

    (require __DIR__ . '/../../database/migrations/2025_12_15_000001_alter_action_events_user_id_nullable.php')->up();

    $columns = collect(Schema::getColumns('action_events'))->keyBy('name');

    expect($columns['user_id']['nullable'])->toBeTrue();
});
