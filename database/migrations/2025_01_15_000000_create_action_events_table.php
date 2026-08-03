<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Applications migrated from Nova already own an `action_events` table,
     * created by a migration whose filename differs from this one. The migrator
     * keys off the filename, so it sees this migration as pending and would
     * fail with "table already exists". Adopt the existing table instead: the
     * package only needs it to be there, and reshaping a table it did not
     * create would clobber application-specific customisations.
     */
    public function up(): void
    {
        if (Schema::hasTable('action_events')) {
            return;
        }

        Schema::create('action_events', function (Blueprint $table) {
            $table->id();
            $table->char('batch_id', 36);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('actionable_type');
            $table->unsignedBigInteger('actionable_id')->nullable();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('fields');
            $table->string('status', 25)->default('running');
            $table->text('exception')->nullable();
            $table->text('original')->nullable();
            $table->text('changes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['actionable_type', 'actionable_id']);
            $table->index(['batch_id', 'model_type', 'model_id']);
            $table->index('user_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Since `up()` adopts a pre-existing table rather than creating it, `down()`
     * must not drop what it never created — that would wipe an application's
     * audit history on the rollback of a migration that did nothing. Two
     * independent signals mark the table as the application's; either one is
     * enough to leave it alone. Erring towards an orphan empty table is cheap,
     * erring towards a dropped audit log is not.
     */
    public function down(): void
    {
        if (! Schema::hasTable('action_events')) {
            return;
        }

        if ($this->applicationOwnsTable() || DB::table('action_events')->exists()) {
            return;
        }

        Schema::drop('action_events');
    }

    /**
     * Whether another recorded migration also creates `action_events`, which
     * means the application owned the table before this package was installed.
     */
    private function applicationOwnsTable(): bool
    {
        $migrations = config('database.migrations', 'migrations');
        $table = is_array($migrations) ? ($migrations['table'] ?? 'migrations') : $migrations;

        if (! Schema::hasTable($table)) {
            return false;
        }

        return DB::table($table)
            ->where('migration', 'like', '%create_action_events_table')
            ->where('migration', '!=', basename(__FILE__, '.php'))
            ->exists();
    }
};