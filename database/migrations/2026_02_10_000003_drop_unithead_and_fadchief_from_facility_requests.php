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
        Schema::table('facility_requests', function (Blueprint $table) {
            if (Schema::hasColumn('facility_requests', 'unitheadapproval')) {
                $table->dropColumn('unitheadapproval');
            }
            if (Schema::hasColumn('facility_requests', 'fadchiefapproval')) {
                $table->dropColumn('fadchiefapproval');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facility_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('facility_requests', 'unitheadapproval')) {
                $table->string('unitheadapproval', 50)->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('facility_requests', 'fadchiefapproval')) {
                $table->string('fadchiefapproval', 50)->nullable()->after('unitheadapproval');
            }
        });
    }
};
