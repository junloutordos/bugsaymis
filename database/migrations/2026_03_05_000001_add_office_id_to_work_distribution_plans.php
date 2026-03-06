<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_distribution_plans', function (Blueprint $table) {
            $table->foreignId('office_id')
                  ->nullable()
                  ->after('office_involved')
                  ->constrained('offices')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_distribution_plans', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Office::class);
            $table->dropColumn('office_id');
        });
    }
};
