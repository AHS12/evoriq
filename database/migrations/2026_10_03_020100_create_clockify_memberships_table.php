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
        Schema::create('clockify_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->foreignId('user_id')->constrained('clockify_users')->cascadeOnDelete();
            $table->string('membership_type');
            $table->string('membership_status')->nullable();
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->decimal('hourly_rate_amount', 12, 2)->nullable();
            $table->string('hourly_rate_currency', 8)->nullable();
            $table->decimal('cost_rate_amount', 12, 2)->nullable();
            $table->string('cost_rate_currency', 8)->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'workspace_id', 'user_id'], 'clockify_memberships_user_index');
            $table->index(['membership_type', 'target_type', 'target_id'], 'clockify_memberships_target_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_memberships');
    }
};
