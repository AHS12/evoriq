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
        Schema::create('clockify_user_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->foreignId('user_id')->constrained('clockify_users')->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained('clockify_custom_fields')->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'workspace_id', 'user_id', 'custom_field_id'],
                'clockify_user_cf_values_unique',
            );
            $table->index(['workspace_id', 'custom_field_id'], 'clockify_user_cf_values_field_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_user_custom_field_values');
    }
};
