<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('Linked system user account, if any');
            $table->string('name', 200);
            $table->string('email', 255)->nullable();
            $table->string('mobile_phone', 20)->nullable();
            $table->string('fcm_device_token', 500)->nullable()->comment('Firebase Cloud Messaging token for push notifications');
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_push')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_contacts');
    }
};
