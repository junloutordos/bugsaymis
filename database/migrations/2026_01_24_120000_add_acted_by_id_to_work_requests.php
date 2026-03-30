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
            if (! Schema::hasColumn('work_requests', 'acted_by_id')) {
                $table->unsignedBigInteger('acted_by_id')->nullable()->after('assigned_user_id');
                $table->foreign('acted_by_id')->references('id')->on('users')->onDelete('set null');
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
            if (Schema::hasColumn('work_requests', 'acted_by_id')) {
                $table->dropForeign(['acted_by_id']);
                $table->dropColumn('acted_by_id');
            }
        });
    }
};
