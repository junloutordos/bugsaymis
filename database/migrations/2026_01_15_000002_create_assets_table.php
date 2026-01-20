<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('property_no')->unique();
            $table->string('asset_name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('condition')->default('Good');
            $table->unsignedBigInteger('location_division_id')->nullable();
            $table->unsignedBigInteger('location_office_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->date('warranty_until')->nullable();
            $table->string('status')->default('Active');
            $table->text('remarks')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();

            $table->foreign('location_division_id')->references('id')->on('divisions')->onDelete('set null');
            $table->foreign('location_office_id')->references('id')->on('offices')->onDelete('set null');
            $table->foreign('assigned_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('assets');
    }
};
