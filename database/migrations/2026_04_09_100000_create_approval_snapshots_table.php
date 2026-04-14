<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_snapshots', function (Blueprint $table) {
            $table->id();

            // Polymorphic parent (LeaveApplication, PayrollRun, Procurement, etc.)
            $table->string('approvable_type', 100);
            $table->unsignedBigInteger('approvable_id');

            // Step identifier and ordering
            $table->string('step', 80);                        // e.g. hr_officer, division_chief
            $table->unsignedTinyInteger('sequence')->default(0); // 1, 2, 3 for display order

            // Decision recorded
            $table->string('action', 30);                      // certified/forwarded/approved/rejected
            $table->string('status', 30)->default('approved'); // approved/rejected/pending

            // Soft user reference — intentionally NOT a FK; user may be deleted
            $table->unsignedBigInteger('user_id')->nullable();

            // ── SNAPSHOT columns — captured at signing time, never mutated ──
            $table->string('name_snapshot');
            $table->string('position_snapshot')->nullable();
            $table->string('division_snapshot', 150)->nullable();
            $table->string('office_snapshot', 150)->nullable();

            $table->text('remarks')->nullable();
            $table->timestamp('signed_at');

            // created_at only — no updated_at (immutability marker)
            $table->timestamp('created_at')->useCurrent();

            // Primary query pattern: load all snapshots for a single record
            $table->index(['approvable_type', 'approvable_id'], 'idx_approval_approvable');
            $table->index('user_id', 'idx_approval_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_snapshots');
    }
};
