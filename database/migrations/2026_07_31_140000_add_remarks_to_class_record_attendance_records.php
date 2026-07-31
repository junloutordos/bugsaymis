<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_record_attendance_records', function (Blueprint $table) {
            $table->string('remarks', 500)->nullable()->after('incomplete_uniform');
        });
    }

    public function down(): void
    {
        Schema::table('class_record_attendance_records', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
