<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('facility_request_id')->nullable()->after('itjr_no');
            $table->unique('facility_request_id');
            $table->foreign('facility_request_id')->references('id')->on('facility_requests')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('it_job_requests', function (Blueprint $table) {
            $table->dropForeign(['facility_request_id']);
            $table->dropUnique(['facility_request_id']);
            $table->dropColumn('facility_request_id');
        });
    }
};
