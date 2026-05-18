<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('subsistence_allowance', 14, 2)->default(0)->after('sala');
            $table->decimal('laundry_allowance', 14, 2)->default(0)->after('subsistence_allowance');
            $table->decimal('ob_travel_seminar', 14, 2)->default(0)->after('pvp_overpayment');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['subsistence_allowance', 'laundry_allowance', 'ob_travel_seminar']);
        });
    }
};
