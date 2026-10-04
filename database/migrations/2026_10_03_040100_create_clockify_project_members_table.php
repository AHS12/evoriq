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
        Schema::create('clockify_project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->foreignId('project_id')->constrained('clockify_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('clockify_users')->cascadeOnDelete();
            $table->string('membership_type')->default('project');
            $table->string('membership_status')->nullable();
            $table->decimal('hourly_rate_amount', 12, 2)->nullable();
            $table->string('hourly_rate_currency', 8)->nullable();
            $table->decimal('cost_rate_amount', 12, 2)->nullable();
            $table->string('cost_rate_currency', 8)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'workspace_id', 'project_id', 'user_id'],
                'clockify_project_members_unique',
            );
            $table->index(['workspace_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_project_members');
    }
};
