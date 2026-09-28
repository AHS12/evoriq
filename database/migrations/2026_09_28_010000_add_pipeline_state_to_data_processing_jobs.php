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
            // How many times the run has been picked up (attempts).
            $table->unsignedSmallInteger('attempt')->default(0)->after('status');

            // Liveness: refreshed on every progress write and stage change.
            $table->timestamp('last_heartbeat_at')->nullable()->after('started_at');

            // When the next retry is due (null unless a retry is scheduled).
            $table->timestamp('next_retry_at')->nullable()->after('last_heartbeat_at');

            // Classified failure (App\Enums\PipelineFailureReason).
            $table->string('failure_reason')->nullable()->after('error_message');

            // Stale-run sweep hot path (status + heartbeat).
            $table->index(['status', 'last_heartbeat_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_processing_jobs', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_heartbeat_at']);
            $table->dropColumn([
                'attempt',
                'last_heartbeat_at',
                'next_retry_at',
                'failure_reason',
            ]);
        });
    }
};
