<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('work_requests', function (Blueprint $table) {
            $table->id();
            $table->string('issue');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('priority')->default('Normal');
            $table->unsignedBigInteger('location_division_id')->nullable();
            $table->unsignedBigInteger('location_office_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->string('status')->default('Pending');
            $table->timestamps();

            $table->foreign('location_division_id')->references('id')->on('divisions')->onDelete('set null');
            $table->foreign('location_office_id')->references('id')->on('offices')->onDelete('set null');
            $table->foreign('assigned_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('requester_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('work_requests');
    }
};
