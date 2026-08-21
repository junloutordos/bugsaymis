<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            // Own work contact number, used by the SOS module to SMS this
            // employee when they're an assigned responder. Distinct from
            // emergency_contact_phone, which is someone ELSE'S number.
            //
            // No ->after() positional hint: emergency_contact_phone is listed
            // in EmployeeProfile::$fillable but has no tracked migration in
            // this codebase and doesn't exist in every environment (confirmed
            // absent in dev) — a schema-drift gap that predates this feature
            // and is out of scope here. Anchoring to a column that may not
            // exist would make this migration fail in some environments.
            $table->string('mobile_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('mobile_number');
        });
    }
};
