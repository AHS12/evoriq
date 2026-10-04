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
        Schema::create('clockify_api_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('connection_id')->constrained('clockify_connections')->cascadeOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();

            $table->string('window_type');
            $table->timestamp('window_started_at');
            $table->timestamp('window_ends_at');
            $table->unsignedInteger('requests_used')->default(0);
            $table->unsignedInteger('requests_remaining')->nullable();
            $table->unsignedInteger('limit_requests');
            $table->timestamp('last_request_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['connection_id', 'workspace_id', 'window_type', 'window_started_at'],
                'clockify_api_usage_window_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_api_usage');
    }
};
