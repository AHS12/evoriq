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
        Schema::table('data_processing_jobs', function (Blueprint $table) {
            // Producer-supplied title + current step (multi-stage reports).
            $table->string('name')->nullable()->after('type');
            $table->string('stage')->nullable()->after('status');

            // Imported source file (import input); `file_*` stays the output artifact.
            $table->string('input_disk')->nullable()->after('filters');
            $table->string('input_path')->nullable()->after('input_disk');
            $table->unsignedBigInteger('input_size')->nullable()->after('input_path');
            $table->string('input_mime_type')->nullable()->after('input_size');

            // Cooperative cancellation (checked at chunk/stage boundaries).
            $table->timestamp('cancel_requested_at')->nullable()->after('completed_at');

            // "My active jobs" (sidebar badge) hot path.
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_processing_jobs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn([
                'name',
                'stage',
                'input_disk',
                'input_path',
                'input_size',
                'input_mime_type',
                'cancel_requested_at',
            ]);
        });
    }
};
