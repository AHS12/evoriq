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
        Schema::create('clockify_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('connection_id')->constrained('clockify_connections')->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();

            $table->string('trigger');
            $table->string('mode');
            $table->string('priority')->default('normal');
            $table->string('status')->default('pending');

            $table->timestamp('range_start')->nullable();
            $table->timestamp('range_end')->nullable();
            $table->json('plan')->nullable();

            $table->unsignedInteger('total_jobs')->default(0);
            $table->unsignedInteger('completed_jobs')->default(0);
            $table->unsignedBigInteger('records_created')->default(0);
            $table->unsignedBigInteger('records_updated')->default(0);
            $table->unsignedBigInteger('records_deleted')->default(0);
            $table->unsignedInteger('api_requests_used')->default(0);

            $table->text('error_message')->nullable();
            $table->string('correlation_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['connection_id', 'status']);
            $table->index('correlation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_sync_runs');
    }
};
