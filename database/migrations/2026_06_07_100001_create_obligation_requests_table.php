<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ors_number')->nullable();
            $table->unsignedBigInteger('procurement_id')->nullable();
            $table->string('po_number')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('account_title')->nullable();
            $table->string('object_code')->nullable();
            $table->string('activity_title')->nullable();
            $table->date('activity_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('created_by');

            $table->string('status')->default('draft');

            // eLog data (entered by End User before forwarding to Budget Officer)
            $table->timestamp('logged_at')->nullable();
            $table->string('log_po_number')->nullable();
            $table->string('log_activity_title')->nullable();
            $table->date('log_activity_date')->nullable();
            $table->decimal('log_amount', 12, 2)->nullable();

            // Division Chief (Step 4)
            $table->unsignedBigInteger('division_chief_id')->nullable();
            $table->timestamp('division_chief_at')->nullable();

            // Budget Officer (Steps 7–8: verify + assign ORS number)
            $table->unsignedBigInteger('budget_officer_id')->nullable();
            $table->timestamp('budget_officer_at')->nullable();
            $table->text('budget_officer_remarks')->nullable();
            $table->timestamp('budget_officer_returned_at')->nullable();
            $table->text('budget_officer_return_reason')->nullable();

            // Bookkeeper (Steps 10–11)
            $table->unsignedBigInteger('bookkeeper_id')->nullable();
            $table->timestamp('bookkeeper_at')->nullable();
            $table->text('bookkeeper_remarks')->nullable();
            $table->timestamp('bookkeeper_returned_at')->nullable();
            $table->text('bookkeeper_return_reason')->nullable();

            // Accountant (Step 12)
            $table->unsignedBigInteger('accountant_id')->nullable();
            $table->timestamp('accountant_at')->nullable();
            $table->text('accountant_remarks')->nullable();

            // OCD (Step 14)
            $table->unsignedBigInteger('ocd_id')->nullable();
            $table->timestamp('ocd_at')->nullable();

            // Canvaser (Steps 16–17)
            $table->unsignedBigInteger('canvaser_id')->nullable();
            $table->timestamp('canvaser_at')->nullable();

            $table->timestamps();

            $table->foreign('procurement_id')->references('id')->on('procurements')->nullOnDelete();
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('division_chief_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('budget_officer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('bookkeeper_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('accountant_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('ocd_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('canvaser_id')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_requests');
    }
};
