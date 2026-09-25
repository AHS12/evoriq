<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            // "Everything this user did" — causer filter, newest first.
            $table->index(['causer_id', 'created_at'], 'idx_audit_causer_time');

            // "Everything that happened to this record" — subject drill-down.
            $table->index(['subject_type', 'subject_id', 'created_at'], 'idx_audit_subject_time');

            // "A channel across a date window" — auth, security, rbac, ...
            $table->index(['log_name', 'created_at'], 'idx_audit_logname_time');

            // Cursor pagination keyset anchor (global newest-first list).
            $table->index(['created_at', 'id'], 'idx_audit_cursor');
        });
    }
};
