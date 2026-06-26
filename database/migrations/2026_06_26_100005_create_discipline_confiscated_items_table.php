<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discipline_confiscated_items')) {
            return;
        }

        Schema::create('discipline_confiscated_items', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();                    // CONF-YYYY-MM-NNN
            $table->unsignedBigInteger('discipline_case_id')->nullable()->index();
            $table->unsignedInteger('student_id')->index();            // app-level FK to students
            $table->text('item_description');
            $table->string('place')->nullable();
            $table->text('reason')->nullable();

            $table->unsignedBigInteger('confiscated_by')->nullable();
            $table->timestamp('confiscated_at')->nullable();

            $table->string('status')->default('held')->index();        // held | claimed | disposed

            // Claim (Item Claim Form) — acknowledged by adviser/counselor/dorm
            $table->timestamp('claimed_at')->nullable();
            $table->string('claim_ack_role')->nullable();              // adviser | counselor | dorm
            $table->unsignedBigInteger('claim_ack_by')->nullable();
            $table->text('claim_remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_confiscated_items');
    }
};
