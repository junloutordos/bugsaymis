<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ict_remote_help_events')) {
            return;
        }

        Schema::create('ict_remote_help_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event'); // created | claimed | credentials_ready | revealed | end_requested | ended | ...
            $table->text('details')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('request_id');

            $table->foreign('request_id')->references('id')->on('ict_remote_help_requests')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ict_remote_help_events');
    }
};
