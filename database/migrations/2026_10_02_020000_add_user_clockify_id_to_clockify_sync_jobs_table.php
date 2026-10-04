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
        Schema::table('clockify_sync_jobs', function (Blueprint $table) {
            // Fact jobs fan out per Clockify user; the external id travels on
            // the job so the runner can rebuild the SyncContext (SYNC-04).
            $table->string('user_clockify_id')->nullable()->after('phase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clockify_sync_jobs', function (Blueprint $table) {
            $table->dropColumn('user_clockify_id');
        });
    }
};
