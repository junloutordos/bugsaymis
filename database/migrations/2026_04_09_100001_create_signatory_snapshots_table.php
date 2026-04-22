<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatory_snapshots', function (Blueprint $table) {
            $table->id();

            // Polymorphic parent (LeaveApplication, PayrollRun, etc.)
            $table->string('signable_type', 100);
            $table->unsignedBigInteger('signable_id');

            // Human-readable role label as it appears on the printed form
            // e.g. "Certifying Officer", "Authorized Officer", "OIC-Campus Director"
            $table->string('role_label', 100);

            // Soft user reference — NOT a FK
            $table->unsignedBigInteger('user_id')->nullable();

            // ── SNAPSHOT columns — locked at form generation time ──
            $table->string('name_snapshot');
            $table->string('position_snapshot')->nullable();
            $table->string('division_snapshot', 150)->nullable();
            $table->string('office_snapshot', 150)->nullable();

            // Path to the e-signature image as it existed at capture time
            $table->string('signature_path', 500)->nullable();

            $table->timestamp('captured_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['signable_type', 'signable_id'], 'idx_signatory_signable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatory_snapshots');
    }
};
