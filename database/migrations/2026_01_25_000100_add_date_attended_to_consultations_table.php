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
        if (Schema::hasTable('consultations') && !Schema::hasColumn('consultations', 'date_attended')) {
            Schema::table('consultations', function (Blueprint $table) {
                $table->dateTime('date_attended')->nullable()->after('scheduled_at');
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
        if (Schema::hasTable('consultations') && Schema::hasColumn('consultations', 'date_attended')) {
            Schema::table('consultations', function (Blueprint $table) {
                $table->dropColumn('date_attended');
            });
        }
    }
};
