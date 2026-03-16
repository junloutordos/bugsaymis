<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('messengerial_requests', function (Blueprint $table) {
            $table->id();
            $table->string('requestor')->nullable();
            $table->string('unit')->nullable();
            $table->string('purpose')->nullable();
            $table->string('destination')->nullable();
            $table->date('date_needed')->nullable();
            $table->time('time_needed')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('Pending');
            $table->text('decline_reason')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('messengerial_requests');
    }
};
