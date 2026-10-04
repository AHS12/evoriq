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
        Schema::create('clockify_raw_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();

            $table->string('entity_type');
            $table->string('clockify_id');
            $table->json('payload');
            $table->string('payload_hash');
            $table->string('source');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(
                ['organization_id', 'workspace_id', 'entity_type', 'clockify_id'],
                'clockify_raw_records_unique',
            );
            $table->index(['entity_type', 'clockify_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_raw_records');
    }
};
