<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('messengerial_requests', 'division_chief_id')) {
            Schema::table('messengerial_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('division_chief_id')->nullable()->after('status');
                $table->foreign('division_chief_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('messengerial_requests', 'division_chief_id')) {
            Schema::table('messengerial_requests', function (Blueprint $table) {
                $table->dropForeign(['division_chief_id']);
                $table->dropColumn('division_chief_id');
            });
        }
    }
};
