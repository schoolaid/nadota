<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('action_events', function (Blueprint $table) {
            // Make exception field nullable
            $table->text('exception')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('action_events', function (Blueprint $table) {
            // Revert to not nullable (not recommended)
            $table->text('exception')->nullable(false)->change();
        });
    }
};