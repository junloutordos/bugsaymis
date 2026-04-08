<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_unit_assignments', function (Blueprint $table) {
            $table->id();

            // ── Core References ───────────────────────────────────────────────
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('Employee being assigned');

            $table->foreignId('organizational_unit_id')
                ->constrained('organizational_units')
                ->cascadeOnDelete()
                ->comment('Unit the employee is assigned to');

            // ── Assignment Classification ─────────────────────────────────────
            $table->boolean('is_primary')->default(true)
                ->comment('True = primary/home unit; False = concurrent/secondary assignment');

            $table->enum('assignment_type', [
                'organic',       // permanent assignment to the unit
                'seconded',      // seconded from another unit
                'designated',    // designated by management order
                'concurrent',    // concurrent with primary assignment
                'detailed',      // detailed for a specific project/period
            ])->default('organic');

            $table->enum('appointment_type', [
                'permanent',     // CSC-approved permanent position
                'temporary',     // temporary appointment
                'casual',        // casual employee
                'cos',           // Contract of Service
                'job_order',     // Job Order
                'secondment',    // Secondment from another agency
            ])->nullable()
                ->comment('Nature of appointment in this unit');

            // ── Position in Unit ──────────────────────────────────────────────
            $table->string('position_title')->nullable()
                ->comment('Official position title within the unit');
            $table->string('item_number', 50)->nullable()
                ->comment('Plantilla item number for permanent positions');

            // ── Effectivity ───────────────────────────────────────────────────
            $table->date('effective_date')
                ->comment('Date the assignment takes effect');
            $table->date('end_date')->nullable()
                ->comment('NULL = currently active assignment');

            // ── Documentary Basis ─────────────────────────────────────────────
            $table->string('order_number', 100)->nullable()
                ->comment('Office Order / Designation Order number');
            $table->text('remarks')->nullable();

            // ── Audit ─────────────────────────────────────────────────────────
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index('user_id');
            $table->index('organizational_unit_id');
            $table->index('is_primary');
            $table->index('effective_date');
            $table->index('end_date');
            $table->index(['user_id', 'is_primary'], 'idx_eua_user_primary');
            $table->index(['organizational_unit_id', 'end_date'], 'idx_eua_unit_active');
            $table->index(['user_id', 'organizational_unit_id', 'end_date'], 'idx_eua_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_unit_assignments');
    }
};
