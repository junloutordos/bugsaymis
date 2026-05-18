<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('midyear_bonus', 14, 2)->default(0)->after('clothing_allowance');
            $table->decimal('cna_incentive', 14, 2)->default(0)->after('midyear_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['midyear_bonus', 'cna_incentive']);
        });
    }
};
