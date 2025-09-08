<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itjr_tracking_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_job_request_id')->constrained()->onDelete('cascade');
            $table->string('status'); // e.g. Submitted, Assessed, In Progress, Completed
            $table->text('remarks')->nullable(); // optional notes by admin
            $table->foreignId('updated_by')->constrained('users')->onDelete('cascade'); // who made the update
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itjr_tracking_logs');
    }
};
