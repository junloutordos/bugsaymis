<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->boolean('is_travel')->default(false)->after('wfh_attendance_id');
            $table->string('travel_remarks', 500)->nullable()->after('is_travel');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->dropColumn(['is_travel', 'travel_remarks']);
        });
    }
};
