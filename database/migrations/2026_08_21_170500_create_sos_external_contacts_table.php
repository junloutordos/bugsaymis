<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_external_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('org')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('alert_types');
            $table->string('channel')->default('sms');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_external_contacts');
    }
};
