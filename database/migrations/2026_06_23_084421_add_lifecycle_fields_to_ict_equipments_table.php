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
        Schema::table('ict_equipments', function (Blueprint $table) {
            $table->date('warranty_expires_at')->nullable()->after('date_acquired');
            $table->string('warranty_provider')->nullable()->after('warranty_expires_at');
            $table->date('decommissioned_at')->nullable()->after('warranty_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ict_equipments', function (Blueprint $table) {
            $table->dropColumn(['warranty_expires_at', 'warranty_provider', 'decommissioned_at']);
        });
    }
};
