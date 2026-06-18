<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->enum('source', ['biometric', 'online_punch', 'wfh', 'manual'])->nullable()
                ->after('wfh_overridden')
                ->comment('Which method supplied the time punches for this record');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
