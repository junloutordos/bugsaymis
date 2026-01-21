<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('work_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('work_requests', 'division_chief_id')) {
                $table->unsignedBigInteger('division_chief_id')->nullable()->after('requester_id');
                $table->foreign('division_chief_id')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('work_requests', function (Blueprint $table) {
            if (Schema::hasColumn('work_requests', 'division_chief_id')) {
                $table->dropForeign(['division_chief_id']);
                $table->dropColumn('division_chief_id');
            }
        });
    }
};
