<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('work_requests', function (Blueprint $table) {
            $table->string('issue')->nullable()->after('id');
            $table->unsignedBigInteger('acted_by_id')->nullable()->after('assigned_user_id');
            $table->date('expected_completion_date')->nullable()->after('acted_by_id');
            $table->text('action_taken')->nullable()->after('expected_completion_date');
            $table->date('date_completed')->nullable()->after('action_taken');

            $table->foreign('acted_by_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('work_requests', function (Blueprint $table) {
            $table->dropForeign(['acted_by_id']);
            $table->dropColumn(['issue','acted_by_id','expected_completion_date','action_taken','date_completed']);
        });
    }
};
