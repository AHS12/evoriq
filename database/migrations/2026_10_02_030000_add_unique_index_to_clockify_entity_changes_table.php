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
        Schema::table('clockify_entity_changes', function (Blueprint $table) {
            // One row per distinct change event so re-scanning a range is
            // idempotent (SYNC-06).
            $table->unique(
                ['organization_id', 'workspace_id', 'entity_type', 'clockify_id', 'change_type', 'source_at'],
                'clockify_entity_changes_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clockify_entity_changes', function (Blueprint $table) {
            $table->dropUnique('clockify_entity_changes_unique');
        });
    }
};
