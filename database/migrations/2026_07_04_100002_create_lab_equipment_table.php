<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone laboratory equipment registry (CID-08/09/10 header fields).
 * `property_no` is an optional cross-reference to the Property module —
 * not a hard FK, to keep this module self-contained.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_equipment', function (Blueprint $table) {
            $table->id();
            $table->string('property_no', 100)->nullable()->index();
            $table->string('description');
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete(); // location
            $table->string('unit', 100)->nullable(); // owning academic unit
            $table->string('category', 100)->nullable();
            $table->date('date_acquired')->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            // active | under_repair | for_calibration | out_of_service | for_disposal | disposed
            $table->string('status', 30)->default('active')->index();
            $table->boolean('calibration_required')->default(false);
            $table->string('calibration_frequency', 30)->nullable(); // Monthly|Quarterly|Semiannual|Yearly
            $table->boolean('pm_required')->default(false);
            $table->string('pm_frequency', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_equipment');
    }
};
