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
        Schema::table('clockify_workspaces', function (Blueprint $table) {
            $table->boolean('default_billable')->nullable()->after('week_start');
            $table->decimal('default_hourly_rate', 12, 2)->nullable()->after('default_billable');
            $table->decimal('default_cost_rate', 12, 2)->nullable()->after('default_hourly_rate');
            $table->json('workspace_settings')->nullable()->after('features');
            $table->string('cake_organization_id')->nullable()->after('feature_subscription_type');
            $table->index('cake_organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clockify_workspaces', function (Blueprint $table) {
            $table->dropIndex(['cake_organization_id']);
            $table->dropColumn([
                'default_billable',
                'default_hourly_rate',
                'default_cost_rate',
                'workspace_settings',
                'cake_organization_id',
            ]);
        });
    }
};
