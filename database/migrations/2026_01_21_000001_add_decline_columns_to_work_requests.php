<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('work_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('work_requests', 'decline_reason')) {
                $table->text('decline_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('work_requests', 'declined_at')) {
                $table->timestamp('declined_at')->nullable()->after('decline_reason');
            }
        });
    }

    public function down()
    {
        Schema::table('work_requests', function (Blueprint $table) {
            if (Schema::hasColumn('work_requests', 'declined_at')) {
                $table->dropColumn('declined_at');
            }
            if (Schema::hasColumn('work_requests', 'decline_reason')) {
                $table->dropColumn('decline_reason');
            }
        });
    }
};
