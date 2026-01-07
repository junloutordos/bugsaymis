<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('facility_requests', 'decline_reason') || ! Schema::hasColumn('facility_requests', 'declined_at')) {
            Schema::table('facility_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('facility_requests', 'decline_reason')) {
                    $table->text('decline_reason')->nullable()->after('status');
                }
                if (! Schema::hasColumn('facility_requests', 'declined_at')) {
                    $table->timestamp('declined_at')->nullable()->after('decline_reason');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('facility_requests', 'decline_reason') || Schema::hasColumn('facility_requests', 'declined_at')) {
            Schema::table('facility_requests', function (Blueprint $table) {
                if (Schema::hasColumn('facility_requests', 'decline_reason')) {
                    $table->dropColumn('decline_reason');
                }
                if (Schema::hasColumn('facility_requests', 'declined_at')) {
                    $table->dropColumn('declined_at');
                }
            });
        }
    }
};
