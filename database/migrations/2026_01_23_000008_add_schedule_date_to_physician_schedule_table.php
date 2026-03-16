<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('physician_schedule', function (Blueprint $table) {
            if (! Schema::hasColumn('physician_schedule', 'schedule_date')) {
                $table->date('schedule_date')->nullable()->after('day');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('physician_schedule', function (Blueprint $table) {
            if (Schema::hasColumn('physician_schedule', 'schedule_date')) {
                $table->dropColumn('schedule_date');
            }
        });
    }
};
