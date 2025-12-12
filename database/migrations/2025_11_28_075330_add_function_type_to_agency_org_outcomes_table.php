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
        Schema::table('agency_org_outcomes', function (Blueprint $table) {
            $table->string('function_type')->nullable()->after('sub_outcome'); 
            // "nullable" in case old records exist
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agency_org_outcomes', function (Blueprint $table) {
            $table->dropColumn('function_type');
        });
    }
};
