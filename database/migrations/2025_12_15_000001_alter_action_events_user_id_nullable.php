<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Integer type names, as reported by `Schema::getColumns()`, across the
     * drivers this package supports.
     *
     * @var list<string>
     */
    private const INTEGER_TYPES = [
        'bigint', 'int', 'integer', 'mediumint', 'smallint', 'tinyint',
        'int2', 'int4', 'int8',
    ];

    /**
     * Columns whose nullability this migration relaxes.
     *
     * @var list<string>
     */
    private const COLUMNS = ['user_id', 'actionable_id', 'target_id'];

    /**
     * Run the migrations.
     *
     * The intent is only to relax nullability. Laravel's `->change()` drops
     * every attribute that is not restated, so the type has to be repeated —
     * which is how this migration used to force `unsignedBigInteger` onto
     * applications that had widened `actionable_id` / `target_id` to strings in
     * order to audit resources with UUID primary keys.
     *
     * So: touch a column only when it is actually non-nullable, and only when
     * its current type is one this migration knows how to restate. Anything
     * else the application has customised is left exactly as it is.
     */
    public function up(): void
    {
        if (! Schema::hasTable('action_events')) {
            return;
        }

        $columns = collect(Schema::getColumns('action_events'))->keyBy('name');

        foreach (self::COLUMNS as $column) {
            $definition = $columns->get($column);

            if ($definition === null || $definition['nullable']) {
                continue;
            }

            if (! in_array($definition['type_name'], self::INTEGER_TYPES, true)) {
                continue;
            }

            Schema::table('action_events', function (Blueprint $table) use ($column) {
                $table->unsignedBigInteger($column)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op. This migration cannot know which columns it
     * actually relaxed, and forcing them back to NOT NULL would either fail
     * against rows that legitimately hold NULL — every action logged without an
     * authenticated user — or reshape a type the application owns. There is
     * nothing to reverse that is worth that risk.
     */
    public function down(): void
    {
        //
    }
};
