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
        if (Schema::hasTable('gatepass')) {
            Schema::table('gatepass', function (Blueprint $table) {
                if (! Schema::hasColumn('gatepass', 'date_time_declined')) {
                    // Match existing pattern for date_time_approved (string length 50)
                    $table->string('date_time_declined', 50)->nullable()->after('remarks');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('gatepass')) {
            Schema::table('gatepass', function (Blueprint $table) {
                if (Schema::hasColumn('gatepass', 'date_time_declined')) {
                    $table->dropColumn('date_time_declined');
                }
            });
        }
    }
};
