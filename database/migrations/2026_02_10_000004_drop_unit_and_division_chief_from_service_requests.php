<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_requests')) {
            Schema::table('service_requests', function (Blueprint $table) {
                if (Schema::hasColumn('service_requests', 'division_chief_id')) {
                    $table->dropColumn('division_chief_id');
                }
                if (Schema::hasColumn('service_requests', 'unit')) {
                    $table->dropColumn('unit');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_requests')) {
            Schema::table('service_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('service_requests', 'unit')) {
                    $table->string('unit')->nullable()->after('requestor_id');
                }
                if (! Schema::hasColumn('service_requests', 'division_chief_id')) {
                    $table->unsignedBigInteger('division_chief_id')->nullable()->after('unit');
                }
            });
        }
    }
};
