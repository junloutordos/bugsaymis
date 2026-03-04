<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_ipcrs_plan', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('sup_average');
        });
    }

    public function down(): void
    {
        Schema::table('employee_ipcrs_plan', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
