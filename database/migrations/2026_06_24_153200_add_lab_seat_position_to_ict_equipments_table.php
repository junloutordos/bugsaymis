<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ict_equipments', function (Blueprint $table) {
            if (!Schema::hasColumn('ict_equipments', 'lab_seat_row')) {
                $table->unsignedTinyInteger('lab_seat_row')->nullable()->after('room_id');
            }
            if (!Schema::hasColumn('ict_equipments', 'lab_seat_col')) {
                $table->unsignedTinyInteger('lab_seat_col')->nullable()->after('lab_seat_row');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ict_equipments', function (Blueprint $table) {
            if (Schema::hasColumn('ict_equipments', 'lab_seat_col')) {
                $table->dropColumn('lab_seat_col');
            }
            if (Schema::hasColumn('ict_equipments', 'lab_seat_row')) {
                $table->dropColumn('lab_seat_row');
            }
        });
    }
};
