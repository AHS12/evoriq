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
        Schema::create('clockify_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clockify_clients')->nullOnDelete();
            $table->string('clockify_id');
            $table->string('name');
            $table->string('color')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->boolean('billable')->default(false);
            $table->boolean('public')->default(true);
            $table->decimal('billable_rate_amount', 12, 2)->nullable();
            $table->string('billable_rate_currency', 8)->nullable();
            $table->decimal('cost_rate_amount', 12, 2)->nullable();
            $table->string('cost_rate_currency', 8)->nullable();
            $table->decimal('estimated_hours', 10, 2)->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'workspace_id', 'clockify_id'], 'clockify_projects_unique');
            $table->index(['workspace_id', 'archived']);
            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_projects');
    }
};
