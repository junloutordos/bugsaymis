<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preventive Maintenance Schedule (PSHS-00-F-CID-08) + per-month status grid,
 * and the Equipment Repair/Service Form (PSHS-00-F-CID-10) running log plus a
 * lightweight corrective-maintenance ticket workflow (Work Request).
 */
return new class extends Migration
{
    public function up(): void
    {
        // CID-08 header (one row per equipment per SY)
        Schema::create('lab_pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->foreignId('lab_equipment_id')->constrained('lab_equipment')->cascadeOnDelete();
            $table->string('frequency', 30); // Monthly | Quarterly | Semiannual | Yearly
            $table->foreignId('prepared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('noted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['school_year_id', 'lab_equipment_id'], 'lab_pm_sched_sy_equip_unq');
        });

        // CID-08 monthly cells (legend: as_scheduled ✓ / not_as_scheduled ○ / not_performed ✗)
        Schema::create('lab_pm_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_pm_schedule_id')->constrained('lab_pm_schedules')->cascadeOnDelete();
            $table->unsignedTinyInteger('month'); // 1-12 calendar month
            $table->string('status', 20)->default('none'); // as_scheduled | not_as_scheduled | not_performed | none
            $table->date('performed_on')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
            $table->unique(['lab_pm_schedule_id', 'month'], 'lab_pm_rec_sched_month_unq');
        });

        // Corrective-maintenance ticket (Work Request) workflow
        Schema::create('lab_repair_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('control_no', 40)->unique();
            $table->foreignId('lab_equipment_id')->nullable()->constrained('lab_equipment')->nullOnDelete();
            $table->foreignId('reported_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('problem');
            $table->string('priority', 10)->default('medium'); // low | medium | high
            $table->text('immediate_action')->nullable();
            $table->string('external_provider', 150)->nullable();
            $table->string('service_report_path')->nullable(); // S3
            $table->boolean('signage_placed')->default(false);
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_reported');
            $table->date('date_completed')->nullable();
            // open | in_repair | resolved | filed | cancelled
            $table->string('status', 20)->default('open')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // CID-10 running service/repair log (per equipment)
        Schema::create('lab_service_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_equipment_id')->constrained('lab_equipment')->cascadeOnDelete();
            $table->foreignId('repair_ticket_id')->nullable()->constrained('lab_repair_tickets')->nullOnDelete();
            $table->date('log_date');
            $table->text('particulars');
            $table->string('type', 20); // preventive | corrective
            $table->string('remarks_reference', 255)->nullable();
            $table->decimal('cost_of_materials', 12, 2)->default(0);
            $table->string('serviced_by', 150)->nullable();
            $table->foreignId('inputted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['lab_equipment_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_service_logs');
        Schema::dropIfExists('lab_repair_tickets');
        Schema::dropIfExists('lab_pm_records');
        Schema::dropIfExists('lab_pm_schedules');
    }
};
