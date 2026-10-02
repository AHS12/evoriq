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
        Schema::create('clockify_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('connection_id')->constrained('clockify_connections')->cascadeOnDelete();
            $table->string('clockify_id');
            $table->string('name');
            $table->string('subdomain')->nullable();
            $table->string('currency')->nullable();
            $table->string('time_zone')->nullable();
            $table->string('week_start')->nullable();
            $table->string('feature_subscription_type')->nullable();
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'clockify_id']);
            $table->index(['connection_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_workspaces');
    }
};
