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
        Schema::create('gatepass', function (Blueprint $table) {
            $table->increments('id');
            $table->string('controlno', 25);
            $table->unsignedInteger('badgeNumber');
            $table->string('gatepass_type', 30);
            $table->string('gatepass_timeout', 20);
            $table->string('gatepass_timein', 20);
            $table->string('gatepass_date', 20);
            $table->string('destination', 100);
            $table->string('purpose', 200);
            $table->string('date_time_approved', 50);
            $table->string('actual_timeout', 50);
            $table->string('actual_timein', 50);
            $table->string('time_consumed', 10);
            $table->string('status', 20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gatepass');
    }
};
