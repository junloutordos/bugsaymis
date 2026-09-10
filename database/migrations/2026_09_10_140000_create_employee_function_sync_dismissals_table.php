<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remembers which auto-synced (source_type=load_assignment)
        // EmployeeFunction rows a user has manually deleted from the
        // Employee Functions page, keyed by the same sync_source_key
        // EmployeeFunctionSyncService::upsertRow() updateOrCreate()s on
        // (e.g. "committee_assignment:45"). Without this, re-sync (which
        // runs automatically on committee create/update, not just the
        // manual "Sync from Faculty Loading" button) recreates an
        // identical row the moment the underlying Load/Committee
        // Assignment is touched again — a manual delete never actually
        // stuck. upsertRow() skips recreating any key present here; the
        // row is a fixed sync_source_key + user_id pair, no FK to the
        // employee_functions row itself (which is already gone by the
        // time this is read back).
        Schema::create('employee_function_sync_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sync_source_key');
            $table->timestamps();

            $table->unique(['user_id', 'sync_source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_function_sync_dismissals');
    }
};
