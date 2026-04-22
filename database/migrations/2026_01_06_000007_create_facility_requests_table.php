<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('facility_requests', function (Blueprint $table) {
            $table->id();
            $table->string('requestor', 100)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('activity', 100)->nullable();
            $table->string('purpose', 255)->nullable();
            $table->string('nature', 100)->nullable();
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->unsignedSmallInteger('male')->nullable();
            $table->unsignedSmallInteger('female')->nullable();
            $table->string('venue', 150)->nullable();
            $table->unsignedSmallInteger('chairs')->nullable();
            $table->unsignedSmallInteger('tables')->nullable();
            $table->unsignedSmallInteger('mic')->nullable();
            $table->unsignedSmallInteger('whiteboard')->nullable();
            $table->unsignedSmallInteger('projector')->nullable();
            $table->unsignedSmallInteger('elecfans')->nullable();
            $table->unsignedSmallInteger('aircon')->nullable();
            $table->unsignedSmallInteger('trashbins')->nullable();
            $table->string('others', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->string('unitheadapproval', 50)->nullable();
            $table->string('fadchiefapproval', 50)->nullable();
            $table->string('status', 50)->nullable()->default('Pending');
            $table->string('email', 150)->nullable();
            $table->timestamp('date_filed')->nullable();
            $table->text('participants')->nullable();
            $table->string('reference_no', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('facility_requests');
    }
};
