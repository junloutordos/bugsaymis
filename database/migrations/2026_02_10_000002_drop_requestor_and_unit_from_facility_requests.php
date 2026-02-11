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
        // Drop legacy requestor (string) and unit columns if they still exist.
        Schema::table('facility_requests', function (Blueprint $table) {
            if (Schema::hasColumn('facility_requests', 'requestor')) {
                $table->dropColumn('requestor');
            }
            if (Schema::hasColumn('facility_requests', 'unit')) {
                $table->dropColumn('unit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('facility_requests', 'requestor')) {
                $table->string('requestor')->nullable()->after('id');
            }
            if (! Schema::hasColumn('facility_requests', 'unit')) {
                $table->string('unit')->nullable()->after('requestor');
            }
        });
    }
};
