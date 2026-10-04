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
        Schema::create('clockify_entity_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();

            $table->string('entity_type');
            $table->string('clockify_id');
            $table->string('change_type');
            $table->timestamp('source_at');
            $table->timestamp('detected_at');
            $table->timestamp('processed_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(
                ['organization_id', 'workspace_id', 'entity_type', 'processed_at'],
                'clockify_entity_changes_lookup',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_entity_changes');
    }
};
