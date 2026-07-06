<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ict_remote_help_requests')) {
            return;
        }

        Schema::create('ict_remote_help_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->text('note')->nullable();

            // waiting → claimed → in_session → completed | canceled | timed_out | failed
            $table->string('status')->default('waiting');

            $table->unsignedBigInteger('it_job_request_id')->nullable();
            $table->unsignedBigInteger('claimed_by')->nullable();
            $table->timestamp('claimed_at')->nullable();

            // AnyDesk connection address reported by the tray. An AnyDesk ID is
            // an address, not a secret (the session is authorized attended — the
            // user clicks Accept on each incoming connection), so it is stored in
            // plaintext; there is no password to rotate.
            $table->string('anydesk_id')->nullable();

            $table->timestamp('session_ready_at')->nullable(); // AnyDesk ID reported
            $table->timestamp('revealed_at')->nullable();      // MIS opened the connect panel
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable();          // ended | canceled | timed_out
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['device_id', 'status']);
            $table->index(['status', 'created_at']);

            $table->foreign('device_id')->references('id')->on('ict_equipment_devices')->onDelete('cascade');
            $table->foreign('claimed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('it_job_request_id')->references('id')->on('it_job_requests')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ict_remote_help_requests');
    }
};
