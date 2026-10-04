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
        Schema::create('clockify_user_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('clockify_workspaces')->nullOnDelete();
            $table->foreignId('user_group_id')->constrained('clockify_user_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('clockify_users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'workspace_id', 'user_group_id', 'user_id'],
                'clockify_user_group_members_unique',
            );
            $table->index(['workspace_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clockify_user_group_members');
    }
};
