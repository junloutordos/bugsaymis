<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('work_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('work_requests', 'action_taken')) {
                $table->text('action_taken')->nullable()->after('expected_completion_date');
            }
            if (! Schema::hasColumn('work_requests', 'date_completed')) {
                $table->date('date_completed')->nullable()->after('action_taken');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('work_requests', function (Blueprint $table) {
            if (Schema::hasColumn('work_requests', 'date_completed')) {
                $table->dropColumn('date_completed');
            }
            if (Schema::hasColumn('work_requests', 'action_taken')) {
                $table->dropColumn('action_taken');
            }
        });
    }
};
