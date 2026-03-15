<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drop clinic_visits and doctor_schedules tables if they exist.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('clinic_visits');
        Schema::dropIfExists('doctor_schedules');
    }

    /**
     * Reverse the migrations.
     * Recreate minimal versions of the tables to allow rollback.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('doctor_schedules')) {
            Schema::create('doctor_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('doctor_id')->nullable();
                $table->date('date')->nullable();
                $table->time('time_start')->nullable();
                $table->time('time_end')->nullable();
                $table->integer('capacity')->default(1);
                $table->string('status')->default('available');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('clinic_visits')) {
            Schema::create('clinic_visits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id')->nullable();
                $table->string('consultation_type')->nullable();
                $table->unsignedBigInteger('doctor_schedule_id')->nullable();
                $table->text('concern')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
    }
};
