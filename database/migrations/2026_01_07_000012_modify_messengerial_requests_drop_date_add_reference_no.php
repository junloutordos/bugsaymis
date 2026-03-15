<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (Schema::hasColumn('messengerial_requests', 'date_needed')) {
                $table->dropColumn('date_needed');
            }
            if (Schema::hasColumn('messengerial_requests', 'time_needed')) {
                $table->dropColumn('time_needed');
            }
            if (! Schema::hasColumn('messengerial_requests', 'reference_no')) {
                $table->string('reference_no')->nullable()->after('destination');
            }
        });
    }

    public function down()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('messengerial_requests', 'date_needed')) {
                $table->date('date_needed')->nullable()->after('destination');
            }
            if (! Schema::hasColumn('messengerial_requests', 'time_needed')) {
                $table->time('time_needed')->nullable()->after('date_needed');
            }
            if (Schema::hasColumn('messengerial_requests', 'reference_no')) {
                $table->dropColumn('reference_no');
            }
        });
    }
};
