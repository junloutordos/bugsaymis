<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->time('penned_time_in_am')->nullable()->after('time_out_pm');
            $table->time('penned_time_out_am')->nullable()->after('penned_time_in_am');
            $table->time('penned_time_in_pm')->nullable()->after('penned_time_out_am');
            $table->time('penned_time_out_pm')->nullable()->after('penned_time_in_pm');
            $table->string('penned_remarks')->nullable()->after('penned_time_out_pm');
            $table->foreignId('penned_by')->nullable()->after('penned_remarks')->constrained('users')->nullOnDelete();
            $table->timestamp('penned_at')->nullable()->after('penned_by');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->dropForeign(['penned_by']);
            $table->dropColumn([
                'penned_time_in_am', 'penned_time_out_am',
                'penned_time_in_pm', 'penned_time_out_pm',
                'penned_remarks', 'penned_by', 'penned_at',
            ]);
        });
    }
};
