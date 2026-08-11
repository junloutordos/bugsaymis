<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_request_tracking_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_request_id')->constrained()->onDelete('cascade');
            $table->string('status'); // e.g. Submitted, Assigned, GSU Approved, FAD Approved, Update, Completed
            $table->text('remarks')->nullable(); // optional notes by requestor/approver/GSU Head
            $table->foreignId('updated_by')->constrained('users')->onDelete('cascade'); // who made the update
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_request_tracking_logs');
    }
};
