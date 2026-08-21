<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EmployeeProfile::$fillable has declared emergency_contact_name/phone for
 * some time, but no migration ever actually added them to the table — a
 * pre-existing schema/model divergence discovered while building the SOS
 * Emergency Button feature, which needs this data to notify a triggering
 * staff member's own emergency contact. Completes what the model already
 * expected; purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone']);
        });
    }
};
