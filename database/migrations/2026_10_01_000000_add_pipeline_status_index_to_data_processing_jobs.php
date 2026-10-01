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
        Schema::table('data_processing_jobs', function (Blueprint $table) {
            // Global pipeline indicator hot path: status counts and the
            // "failed in the last 24h" nudge (PIPE-08).
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_processing_jobs', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });
    }
};
