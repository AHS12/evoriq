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
        Schema::create('clockify_deleted_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();

            $table->string('entity_type');
            $table->string('clockify_id');
            $table->timestamp('deleted_at');
            $table->string('document_code')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'workspace_id', 'entity_type', 'clockify_id'],
                'clockify_deleted_entities_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_deleted_entities');
    }
};
