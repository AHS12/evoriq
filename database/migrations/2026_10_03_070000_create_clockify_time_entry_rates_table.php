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
        Schema::create('clockify_time_entry_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->string('clockify_id')->nullable();
            $table->foreignId('time_entry_id')->constrained('clockify_time_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('clockify_users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('clockify_projects')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('clockify_tasks')->nullOnDelete();
            $table->decimal('billable_rate_amount', 12, 2)->nullable();
            $table->string('billable_rate_currency', 8)->nullable();
            $table->decimal('cost_rate_amount', 12, 2)->nullable();
            $table->string('cost_rate_currency', 8)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'workspace_id', 'time_entry_id'],
                'clockify_time_entry_rates_unique',
            );
            $table->index(['organization_id', 'workspace_id', 'user_id'], 'clockify_time_entry_rates_user_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_time_entry_rates');
    }
};
