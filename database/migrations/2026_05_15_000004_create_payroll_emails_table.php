<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
            $table->enum('send_type', ['initial', 'bonus_update', 'resend'])->default('initial');
            $table->string('to_email');
            $table->string('bcc_email')->nullable();
            $table->string('subject');
            $table->string('message_id')->nullable();
            $table->enum('status', ['queued', 'sending', 'sent', 'failed', 'bounced'])->default('queued');
            $table->tinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('payroll_item_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_emails');
    }
};
