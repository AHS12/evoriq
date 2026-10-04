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
        Schema::create('clockify_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_run_id')->constrained('clockify_sync_runs')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();

            $table->string('entity_type');
            $table->string('phase');

            $table->timestamp('range_start')->nullable();
            $table->timestamp('range_end')->nullable();

            $table->unsignedInteger('page')->default(0);
            $table->unsignedInteger('page_size')->default(200);
            $table->unsignedBigInteger('records_processed')->default(0);
            $table->unsignedBigInteger('records_created')->default(0);
            $table->unsignedBigInteger('records_updated')->default(0);
            $table->unsignedBigInteger('records_deleted')->default(0);

            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->json('checkpoint')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['sync_run_id', 'status']);
            $table->index(['sync_run_id', 'entity_type']);
            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_sync_jobs');
    }
};
