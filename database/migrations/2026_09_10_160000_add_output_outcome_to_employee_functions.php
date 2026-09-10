<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A short Output/Outcome Statement describing the Label itself (the
     * Core/Support Function as a whole) — distinct from success_indicator,
     * which describes a tagged Work Distribution Plan. Manually set here
     * for manually-created functions; auto-synced designation-backed
     * functions inherit it from Designation::description
     * (EmployeeFunctionSyncService::groupByDesignation).
     */
    public function up(): void
    {
        Schema::table('employee_functions', function (Blueprint $table) {
            $table->text('output_outcome')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('employee_functions', function (Blueprint $table) {
            $table->dropColumn('output_outcome');
        });
    }
};
