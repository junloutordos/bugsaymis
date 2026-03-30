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
        Schema::table('consultations', function (Blueprint $table) {
            if (! Schema::hasColumn('consultations', 'day')) {
                $table->enum('day', ['monday','tuesday','wednesday','thursday','friday'])->nullable()->after('status');
            }

            if (! Schema::hasColumn('consultations', 'time_start')) {
                $table->time('time_start')->nullable()->after('day');
            }

            if (! Schema::hasColumn('consultations', 'time_end')) {
                $table->time('time_end')->nullable()->after('time_start');
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
        Schema::table('consultations', function (Blueprint $table) {
            if (Schema::hasColumn('consultations', 'time_end')) {
                $table->dropColumn('time_end');
            }

            if (Schema::hasColumn('consultations', 'time_start')) {
                $table->dropColumn('time_start');
            }

            if (Schema::hasColumn('consultations', 'day')) {
                $table->dropColumn('day');
            }
        });
    }
};
