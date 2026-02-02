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
        Schema::create('schedule', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('badgeNumber');

            $table->string('m_timein', 10);
            $table->string('m_breakout', 10);
            $table->string('m_breakin', 10);
            $table->string('m_timeout', 10);

            $table->string('t_timein', 10);
            $table->string('t_breakout', 10);
            $table->string('t_breakin', 10);
            $table->string('t_timeout', 10);

            $table->string('w_timein', 10);
            $table->string('w_breakout', 10);
            $table->string('w_breakin', 10);
            $table->string('w_timeout', 10);

            $table->string('th_timein', 10);
            $table->string('th_breakout', 10);
            $table->string('th_breakin', 10);
            $table->string('th_timeout', 10);

            $table->string('f_timein', 10);
            $table->string('f_breakout', 10);
            $table->string('f_breakin', 10);
            $table->string('f_timeout', 10);

            $table->string('sat_timein', 10);
            $table->string('sat_breakout', 10);
            $table->string('sat_breakin', 10);
            $table->string('sat_timeout', 10);

            $table->string('sun_timein', 10);
            $table->string('sun_breakout', 10);
            $table->string('sun_breakin', 10);
            $table->string('sun_timeout', 10);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('schedule');
    }
};
