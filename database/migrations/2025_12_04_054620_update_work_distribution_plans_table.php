<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateWorkDistributionPlansTable extends Migration
{
    public function up()
    {
        Schema::table('work_distribution_plans', function (Blueprint $table) {

            // Remove old fields
            if (Schema::hasColumn('work_distribution_plans', 'start_date')) {
                $table->dropColumn('start_date');
            }

            if (Schema::hasColumn('work_distribution_plans', 'end_date')) {
                $table->dropColumn('end_date');
            }

            // Add new fields
            $table->string('office_involved')->nullable()->after('success_indicator');
            $table->string('rated_by')->nullable()->after('office_involved');
        });
    }

    public function down()
    {
        Schema::table('work_distribution_plans', function (Blueprint $table) {

            // Restore removed fields
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Drop newly added fields
            $table->dropColumn('office_involved');
            $table->dropColumn('rated_by');
        });
    }
}

