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
        Schema::create('clockify_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->foreignId('user_id')->constrained('clockify_users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('clockify_projects')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('clockify_tasks')->nullOnDelete();
            $table->string('clockify_id');
            $table->text('description')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('billable')->default(false);
            $table->string('type')->nullable();
            $table->string('time_zone')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_in_progress')->default(false);
            $table->string('approval_status')->nullable();
            $table->decimal('cost_amount', 12, 2)->nullable();
            $table->string('cost_currency', 8)->nullable();
            $table->decimal('billable_amount', 12, 2)->nullable();
            $table->string('billable_currency', 8)->nullable();
            $table->timestamp('clockify_created_at')->nullable();
            $table->timestamp('clockify_updated_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'workspace_id', 'clockify_id'], 'clockify_time_entries_unique');
            $table->index(['organization_id', 'workspace_id', 'user_id', 'start_at'], 'clockify_time_entries_user_start_index');
            $table->index(['organization_id', 'workspace_id', 'project_id', 'start_at'], 'clockify_time_entries_project_start_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_time_entries');
    }
};
