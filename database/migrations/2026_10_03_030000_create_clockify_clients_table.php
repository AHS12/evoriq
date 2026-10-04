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
        Schema::create('clockify_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->string('clockify_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('note')->nullable();
            $table->string('currency_code', 8)->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'workspace_id', 'clockify_id'], 'clockify_clients_unique');
            $table->index(['workspace_id', 'archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_clients');
    }
};
