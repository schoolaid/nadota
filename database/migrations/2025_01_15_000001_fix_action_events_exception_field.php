<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Text type names, as reported by `Schema::getColumns()`, mapped to the
     * blueprint method that reproduces them exactly.
     *
     * @var array<string, string>
     */
    private const TEXT_TYPES = [
        'tinytext' => 'tinyText',
        'text' => 'text',
        'mediumtext' => 'mediumText',
        'longtext' => 'longText',
    ];

    /**
     * Run the migrations.
     *
     * The intent is only to make `exception` nullable. Laravel's `->change()`
     * drops every attribute that is not restated, so hardcoding `text` narrowed
     * the column for applications that had widened it to `mediumtext` or
     * `longtext` — silently truncating the stack traces it exists to store.
     *
     * So: act only when the column is genuinely non-nullable, and restate its
     * current width rather than assuming one. A width this migration cannot
     * reproduce is left untouched.
     */
    public function up(): void
    {
        if (! Schema::hasTable('action_events')) {
            return;
        }

        $exception = collect(Schema::getColumns('action_events'))->firstWhere('name', 'exception');

        if ($exception === null || $exception['nullable']) {
            return;
        }

        $method = self::TEXT_TYPES[$exception['type_name']] ?? null;

        if ($method === null) {
            return;
        }

        Schema::table('action_events', function (Blueprint $table) use ($method) {
            $table->{$method}('exception')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op. This is a corrective migration, and reversing it
     * would force NOT NULL back onto a column that legitimately holds NULL for
     * every action that completed without raising — the change would simply
     * fail, and on the tables where it did not, it would reshape a width the
     * application owns.
     */
    public function down(): void
    {
        //
    }
};
