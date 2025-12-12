<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('employee_ipcrs_plan', function (Blueprint $table) {

            // Accomplishment
            $table->text('accomplishment')->nullable()->after('plan_id');

            // MOV link
            $table->string('mov_link')->nullable()->after('accomplishment');

            // Self Ratings
            $table->tinyInteger('self_quality')->nullable()->after('mov_link');
            $table->tinyInteger('self_efficiency')->nullable()->after('self_quality');
            $table->tinyInteger('self_timeliness')->nullable()->after('self_efficiency');

            // Self Rating Average
            $table->decimal('self_average', 5, 2)->nullable()->after('self_timeliness');

            // Supervisor Ratings
            $table->tinyInteger('sup_quality')->nullable()->after('self_average');
            $table->tinyInteger('sup_efficiency')->nullable()->after('sup_quality');
            $table->tinyInteger('sup_timeliness')->nullable()->after('sup_efficiency');

            // Supervisor Rating Average
            $table->decimal('sup_average', 5, 2)->nullable()->after('sup_timeliness');
        });
    }

    public function down()
    {
        Schema::table('employee_ipcrs_plan', function (Blueprint $table) {
            $table->dropColumn([
                'accomplishment',
                'mov_link',

                'self_quality',
                'self_efficiency',
                'self_timeliness',
                'self_average',

                'sup_quality',
                'sup_efficiency',
                'sup_timeliness',
                'sup_average',
            ]);
        });
    }
};
