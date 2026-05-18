<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('year_end_bonus', 14, 2)->default(0)->after('others_bonuses');
            $table->decimal('cash_gift', 14, 2)->default(0)->after('year_end_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['year_end_bonus', 'cash_gift']);
        });
    }
};
