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
        Schema::table('consultations', function (Blueprint $table) {
            if (! Schema::hasColumn('consultations', 'consultation_type')) {
                $table->string('consultation_type')->nullable()->after('requestor_id');
            }

            if (! Schema::hasColumn('consultations', 'schedule_id')) {
                $table->unsignedBigInteger('schedule_id')->nullable()->after('consultation_type');
                $table->foreign('schedule_id')
                    ->references('id')
                    ->on('physician_schedule')
                    ->onDelete('set null');
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
        Schema::table('consultations', function (Blueprint $table) {
            if (Schema::hasColumn('consultations', 'schedule_id')) {
                $table->dropForeign(['schedule_id']);
                $table->dropColumn('schedule_id');
            }

            if (Schema::hasColumn('consultations', 'consultation_type')) {
                $table->dropColumn('consultation_type');
            }
        });
    }
};
