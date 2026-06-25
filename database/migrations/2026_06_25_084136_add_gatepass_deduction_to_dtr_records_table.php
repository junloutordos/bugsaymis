<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->decimal('gatepass_deduction_minutes', 6, 2)->default(0)->after('overtime_minutes')
                ->comment('Minutes deducted from hours_worked for approved gate passes with recorded actual times');
            $table->decimal('gatepass_undertime_minutes', 6, 2)->default(0)->after('gatepass_deduction_minutes')
                ->comment('Subset of gatepass_deduction_minutes counted toward undertime_minutes (UT-type passes only)');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->dropColumn(['gatepass_deduction_minutes', 'gatepass_undertime_minutes']);
        });
    }
};
