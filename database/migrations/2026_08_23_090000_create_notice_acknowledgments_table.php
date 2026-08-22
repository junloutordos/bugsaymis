<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->string('notice_type');
            $table->unsignedBigInteger('notice_id');
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(
                ['notice_type', 'notice_id', 'recipient_type', 'recipient_id'],
                'notice_ack_unique'
            );
            $table->index(['recipient_type', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_acknowledgments');
    }
};
