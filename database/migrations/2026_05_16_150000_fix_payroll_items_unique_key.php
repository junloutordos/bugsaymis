<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropUnique('payroll_items_per_user_month');
            $table->unique(['batch_id', 'matched_user_id'], 'payroll_items_per_batch_user');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropUnique('payroll_items_per_batch_user');
            $table->unique(['year', 'month', 'matched_user_id'], 'payroll_items_per_user_month');
        });
    }
};
