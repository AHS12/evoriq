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
        Schema::table('clockify_workspaces', function (Blueprint $table) {
            // The last successful change-feed scan time (SYNC-06); advanced and
            // consumed by the incremental sync scheduler (SYNC-11).
            $table->timestamp('change_feed_cursor_at')->nullable()->after('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clockify_workspaces', function (Blueprint $table) {
            $table->dropColumn('change_feed_cursor_at');
        });
    }
};
