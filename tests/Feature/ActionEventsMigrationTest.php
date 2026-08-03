<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nadota ships its own `create_action_events_table` migration and loads it
 * automatically (`ResourceServiceProvider::registerMigrations`). Applications
 * that already own an `action_events` table — anything migrated from Nova,
 * whose migration carries a different filename and is therefore invisible to
 * the migrator — used to hit `SQLSTATE[42S01] ... table already exists` on
 * `php artisan migrate`.
 *
 * The migration must no-op when the table is already there, and must never
 * reshape a table it did not create.
 */
beforeEach(function () {
    Schema::dropIfExists('action_events');
});

it('creates action_events when the application does not have it', function () {
    expect(Schema::hasTable('action_events'))->toBeFalse();

    (require __DIR__ . '/../../database/migrations/2025_01_15_000000_create_action_events_table.php')->up();

    expect(Schema::hasTable('action_events'))->toBeTrue()
        ->and(Schema::hasColumn('action_events', 'batch_id'))->toBeTrue()
        ->and(Schema::hasColumn('action_events', 'actionable_type'))->toBeTrue();
});

it('no-ops when the application already owns an action_events table', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
        $table->string('legacy_marker');
    });

    (require __DIR__ . '/../../database/migrations/2025_01_15_000000_create_action_events_table.php')->up();

    expect(Schema::hasColumn('action_events', 'legacy_marker'))->toBeTrue()
        ->and(Schema::hasColumn('action_events', 'batch_id'))->toBeFalse();
});

it('leaves an application-customised column shape untouched', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
        $table->string('actionable_id', 36)->nullable();
    });

    (require __DIR__ . '/../../database/migrations/2025_01_15_000000_create_action_events_table.php')->up();

    expect(Schema::getColumnType('action_events', 'actionable_id'))->toBe('varchar');
});

/**
 * Because `up()` adopts a table it did not create, `down()` must not drop it —
 * that would destroy the application's audit history on a rollback that never
 * created anything in the first place.
 */
it('drops the table it created itself', function () {
    $migration = require __DIR__ . '/../../database/migrations/2025_01_15_000000_create_action_events_table.php';

    $migration->up();
    $migration->down();

    expect(Schema::hasTable('action_events'))->toBeFalse();
});

it('keeps a table another migration claims ownership of', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
        $table->string('legacy_marker');
    });

    DB::table('migrations')->insert([
        'migration' => '2018_01_01_000000_create_action_events_table',
        'batch' => 1,
    ]);

    (require __DIR__ . '/../../database/migrations/2025_01_15_000000_create_action_events_table.php')->down();

    expect(Schema::hasTable('action_events'))->toBeTrue()
        ->and(Schema::hasColumn('action_events', 'legacy_marker'))->toBeTrue();
});

it('keeps a table that already holds audit rows', function () {
    $migration = require __DIR__ . '/../../database/migrations/2025_01_15_000000_create_action_events_table.php';
    $migration->up();

    DB::table('action_events')->insert([
        'batch_id' => '9f8a1c2e-0000-4000-8000-000000000000',
        'name' => 'Update',
        'actionable_type' => 'App\\Models\\Order',
        'target_type' => 'App\\Models\\Order',
        'model_type' => 'App\\Models\\Order',
        'fields' => '[]',
    ]);

    $migration->down();

    expect(Schema::hasTable('action_events'))->toBeTrue()
        ->and(DB::table('action_events')->count())->toBe(1);
});
