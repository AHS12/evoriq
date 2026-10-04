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
        Schema::create('clockify_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->foreignId('project_id')->constrained('clockify_projects')->cascadeOnDelete();
            $table->foreignId('assignee_user_id')->nullable()->constrained('clockify_users')->nullOnDelete();
            $table->string('clockify_id');
            $table->string('name');
            $table->string('status')->nullable();
            $table->boolean('billable')->default(false);
            $table->decimal('estimated_hours', 10, 2)->nullable();
            $table->decimal('billable_rate_amount', 12, 2)->nullable();
            $table->string('billable_rate_currency', 8)->nullable();
            $table->decimal('cost_rate_amount', 12, 2)->nullable();
            $table->string('cost_rate_currency', 8)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'workspace_id', 'clockify_id'], 'clockify_tasks_unique');
            $table->index(['project_id', 'status']);
            $table->index('assignee_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_tasks');
    }
};
