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
        Schema::create('clockify_time_entry_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->foreignId('time_entry_id')->constrained('clockify_time_entries')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('clockify_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['time_entry_id', 'tag_id'], 'clockify_time_entry_tags_unique');
            $table->index(['workspace_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_time_entry_tags');
    }
};
