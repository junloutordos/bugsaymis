<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursement_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('dv_number')->nullable();
            $table->unsignedBigInteger('ors_id')->nullable();
            $table->string('po_number')->nullable();
            $table->string('activity_title')->nullable();
            $table->date('activity_date')->nullable();
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->string('payment_method')->nullable(); // ada | cheque
            $table->unsignedBigInteger('created_by');
            $table->string('status')->default('draft');

            // Phase A: Delivery, Inspection & Acceptance
            $table->string('iar_number')->nullable();
            $table->string('ris_number')->nullable();
            $table->string('dr_number')->nullable();
            $table->timestamp('delivery_accepted_at')->nullable();
            $table->boolean('ris_complete')->default(false);
            $table->unsignedBigInteger('supply_officer_id')->nullable();

            // Phase B: DV Preparation
            $table->unsignedBigInteger('dv_prepared_by')->nullable();
            $table->timestamp('dv_prepared_at')->nullable();
            // Division Chief logs in eLog, Division Clerk forwards
            $table->unsignedBigInteger('division_chief_id')->nullable();
            $table->timestamp('division_chief_elog_at')->nullable();
            $table->unsignedBigInteger('division_clerk_id')->nullable();
            $table->timestamp('division_clerk_at')->nullable();

            // Phase C: Bookkeeper Verification
            $table->unsignedBigInteger('bookkeeper_id')->nullable();
            $table->timestamp('bookkeeper_at')->nullable();
            $table->text('bookkeeper_remarks')->nullable();
            $table->timestamp('bookkeeper_returned_at')->nullable();
            $table->text('bookkeeper_return_reason')->nullable();

            // Phase D: Accountant Review
            $table->unsignedBigInteger('accountant_id')->nullable();
            $table->timestamp('accountant_at')->nullable();
            $table->text('accountant_remarks')->nullable();
            $table->timestamp('accountant_returned_at')->nullable();
            $table->text('accountant_return_reason')->nullable();

            // Phase E: OCD Signing
            $table->unsignedBigInteger('ocd_sign_id')->nullable();
            $table->timestamp('ocd_sign_at')->nullable();

            // Phase F: Cashier Payment Processing
            $table->unsignedBigInteger('cashier_id')->nullable();
            $table->timestamp('cashier_processed_at')->nullable();
            $table->decimal('cashier_amount', 12, 2)->nullable();

            // Phase G: OCD Payment Signature
            $table->timestamp('ocd_payment_sign_at')->nullable();

            // Phase H: Payment Release
            $table->timestamp('payment_released_at')->nullable();
            $table->string('payment_reference')->nullable();

            $table->timestamps();

            $table->foreign('ors_id')->references('id')->on('obligation_requests')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('supply_officer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('dv_prepared_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('division_chief_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('division_clerk_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('bookkeeper_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('accountant_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('ocd_sign_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cashier_id')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_vouchers');
    }
};
