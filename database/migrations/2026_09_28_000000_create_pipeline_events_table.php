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
        Schema::create('pipeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            // The owning run: `run_type` names the run table, `run_id` is its
            // key as a string so any run table can share this stream.
            $table->string('run_type');
            $table->string('run_id');

            // Per-run allocation owned by PipelineEventRecorder.
            $table->unsignedBigInteger('sequence');

            $table->string('type');
            $table->string('level')->default('info');
            $table->string('stage')->nullable();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->json('progress')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();

            // `occurred_at` is the logical time the step finished; `created_at`
            // is the insert time. The stream is append-only (no `updated_at`).
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            // Guarantees an ordered, gap-tolerant log with no duplicate
            // sequences under concurrent writes.
            $table->unique(
                ['organization_id', 'run_type', 'run_id', 'sequence'],
                'pipeline_events_run_sequence_unique',
            );

            // Timeline reads for a single run.
            $table->index(
                ['organization_id', 'run_type', 'run_id', 'occurred_at'],
                'pipeline_events_run_timeline_index',
            );

            // Error filtering.
            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_events');
    }
};
