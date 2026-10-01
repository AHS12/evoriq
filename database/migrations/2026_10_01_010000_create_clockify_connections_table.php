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
        Schema::create('clockify_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();

            $table->string('name');
            $table->text('api_key'); // encrypted cast
            $table->text('addon_token')->nullable(); // encrypted cast
            $table->string('region')->default('global');
            $table->string('base_url');
            $table->string('reports_base_url');
            $table->string('subdomain')->nullable();
            $table->string('workspace_id')->nullable();
            $table->string('feature_subscription_type')->nullable();
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('webhook_limit')->nullable();
            $table->unsignedInteger('requests_per_hour')->nullable();
            $table->unsignedInteger('requests_per_second')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_connections');
    }
};
