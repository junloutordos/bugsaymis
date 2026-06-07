<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligation_requests', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')
                  ->nullable()
                  ->after('procurement_id')
                  ->constrained('purchase_orders')
                  ->nullOnDelete()
                  ->comment('Set automatically when ORS is created from a PR with an issued PO');
        });
    }

    public function down(): void
    {
        Schema::table('obligation_requests', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn('purchase_order_id');
        });
    }
};
