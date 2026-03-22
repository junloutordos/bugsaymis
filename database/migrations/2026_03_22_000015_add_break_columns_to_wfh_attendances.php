<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wfh_attendances', function (Blueprint $table) {
            // Break Out = start of lunch break (recorded after time_in)
            $table->timestamp('break_out')->nullable()->after('time_in_photo_link');
            // Break In  = return from lunch break (recorded after break_out)
            $table->timestamp('break_in')->nullable()->after('break_out');
        });
    }

    public function down(): void
    {
        Schema::table('wfh_attendances', function (Blueprint $table) {
            $table->dropColumn(['break_out', 'break_in']);
        });
    }
};
