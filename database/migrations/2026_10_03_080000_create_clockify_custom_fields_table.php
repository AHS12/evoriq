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
        Schema::create('clockify_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->string('clockify_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('entity_type');
            $table->string('status')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('only_admin_can_edit')->default(false);
            $table->json('allowed_values')->nullable();
            $table->string('placeholder', 1024)->nullable();
            $table->json('workspace_default_value')->nullable();
            $table->json('project_default_values')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'workspace_id', 'clockify_id'], 'clockify_custom_fields_unique');
            $table->index(['workspace_id', 'entity_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_custom_fields');
    }
};
