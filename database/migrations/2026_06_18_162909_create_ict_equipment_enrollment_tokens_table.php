<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIctEquipmentEnrollmentTokensTable extends Migration
{
    public function up()
    {
        Schema::create('ict_equipment_enrollment_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('created_by')->index();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ict_equipment_enrollment_tokens');
    }
}
