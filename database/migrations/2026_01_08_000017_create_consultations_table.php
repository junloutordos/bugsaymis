<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->string('requestor')->nullable();
            $table->string('email')->nullable();
            $table->string('unit')->nullable();
            $table->text('reason')->nullable();
            $table->string('contact')->nullable();
            $table->string('status')->default('Pending');
            $table->unsignedBigInteger('nurse_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('consultations');
    }
};
