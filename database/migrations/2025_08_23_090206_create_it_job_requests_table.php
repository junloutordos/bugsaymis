<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('it_job_requests', function (Blueprint $table) {
            $table->id();
            $table->string('itjr_no');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Requestor
            $table->string('category'); // e.g., Hardware, Software, Network, Account
            $table->string('title'); // Short description
            $table->text('description'); // Full problem details
            $table->string('status')->default('For Approval of DC'); // Pending, In Progress, Completed, Cancelled
            $table->string('divisionchief'); // Division Chief Approver
            $table->string('assignedto');//MIS Assigned Personnel
            $table->date('dc_approval_date')->nullable();
            $table->date('ocd_approval_date')->nullable();
            $table->text('mis_assessment')->nullable(); // MIS_Admin assessment notes
            $table->date('expected_completion_date')->nullable();
            $table->text('action_taken')->nullable(); // Action Details
            $table->date('completed_at')->nullable();
            $table->string('attendedby')->nullable(); 
            $table->integer('feedback')->default(0)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('it_job_requests');
    }
};
