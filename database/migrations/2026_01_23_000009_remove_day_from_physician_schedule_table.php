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
            if (Schema::hasColumn('physician_schedule', 'day')) {
                $table->dropColumn('day');
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
            if (! Schema::hasColumn('physician_schedule', 'day')) {
                $table->enum('day', ['monday','tuesday','wednesday','thursday','friday'])->nullable()->after('id');
            }
        });
    }
};
