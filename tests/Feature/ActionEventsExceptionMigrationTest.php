<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `fix_action_events_exception_field` only means to relax nullability on
 * `exception`. Restating `text` as part of `->change()` narrows the column for
 * any application that widened it to `mediumtext` / `longtext`, silently
 * truncating the stack traces it exists to store.
 *
 * SQLite collapses every text width down to `text`, so width preservation
 * itself is not observable here. What these tests pin down is the decision: the
 * migration must emit no DDL at all when there is nothing to relax, and must
 * never restate a type it does not know how to reproduce.
 */
beforeEach(function () {
    Schema::dropIfExists('action_events');
});

/**
 * @return list<string>
 */
function schemaChangingQueries(Closure $callback): array
{
    DB::enableQueryLog();
    DB::flushQueryLog();

    $callback();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query) => (bool) preg_match('/^\s*(create|alter|drop)\s/i', $query))
        ->values()
        ->all();

    DB::disableQueryLog();

    return $queries;
}

it('makes a non-nullable exception column nullable', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
        $table->text('exception');
    });

    (require __DIR__ . '/../../database/migrations/2025_01_15_000001_fix_action_events_exception_field.php')->up();

    $exception = collect(Schema::getColumns('action_events'))->firstWhere('name', 'exception');

    expect($exception['nullable'])->toBeTrue();
});

it('emits no schema change when the column is already nullable', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
        $table->text('exception')->nullable();
    });

    $queries = schemaChangingQueries(function () {
        (require __DIR__ . '/../../database/migrations/2025_01_15_000001_fix_action_events_exception_field.php')->up();
    });

    expect($queries)->toBeEmpty();
});

it('does not restate a type it cannot reproduce', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
        $table->string('exception', 500);
    });

    (require __DIR__ . '/../../database/migrations/2025_01_15_000001_fix_action_events_exception_field.php')->up();

    expect(Schema::getColumnType('action_events', 'exception'))->toBe('varchar');
});

it('tolerates a table without an exception column', function () {
    Schema::create('action_events', function (Blueprint $table) {
        $table->id();
    });

    (require __DIR__ . '/../../database/migrations/2025_01_15_000001_fix_action_events_exception_field.php')->up();

    expect(Schema::hasColumn('action_events', 'exception'))->toBeFalse();
});

it('tolerates the table not existing at all', function () {
    (require __DIR__ . '/../../database/migrations/2025_01_15_000001_fix_action_events_exception_field.php')->up();

    expect(Schema::hasTable('action_events'))->toBeFalse();
});
