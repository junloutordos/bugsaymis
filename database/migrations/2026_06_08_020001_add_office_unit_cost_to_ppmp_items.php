<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            // For Part II catalogue items the office supplies their own price.
            // Null = use catalogue unit_cost (Part I behaviour).
            $table->decimal('office_unit_cost', 15, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropColumn('office_unit_cost');
        });
    }
};
