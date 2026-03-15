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
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('messengerial_requests', 'rfsf_reference_no')) {
                $table->string('rfsf_reference_no')->nullable()->after('proof_of_delivery');
            }
            if (! Schema::hasColumn('messengerial_requests', 'courier_service_provider')) {
                $table->string('courier_service_provider')->nullable()->after('rfsf_reference_no');
            }
            if (! Schema::hasColumn('messengerial_requests', 'courier_cost')) {
                $table->decimal('courier_cost', 10, 2)->nullable()->after('courier_service_provider');
            }
            if (! Schema::hasColumn('messengerial_requests', 'date_received_by_courier')) {
                $table->dateTime('date_received_by_courier')->nullable()->after('courier_cost');
            }
            if (! Schema::hasColumn('messengerial_requests', 'date_delivered')) {
                $table->dateTime('date_delivered')->nullable()->after('date_received_by_courier');
            }
            if (! Schema::hasColumn('messengerial_requests', 'proof_remarks')) {
                $table->text('proof_remarks')->nullable()->after('date_delivered');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (Schema::hasColumn('messengerial_requests', 'proof_remarks')) {
                $table->dropColumn('proof_remarks');
            }
            if (Schema::hasColumn('messengerial_requests', 'date_delivered')) {
                $table->dropColumn('date_delivered');
            }
            if (Schema::hasColumn('messengerial_requests', 'date_received_by_courier')) {
                $table->dropColumn('date_received_by_courier');
            }
            if (Schema::hasColumn('messengerial_requests', 'courier_cost')) {
                $table->dropColumn('courier_cost');
            }
            if (Schema::hasColumn('messengerial_requests', 'courier_service_provider')) {
                $table->dropColumn('courier_service_provider');
            }
            if (Schema::hasColumn('messengerial_requests', 'rfsf_reference_no')) {
                $table->dropColumn('rfsf_reference_no');
            }
        });
    }
};
