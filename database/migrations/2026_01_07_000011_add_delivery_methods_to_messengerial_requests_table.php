<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('messengerial_requests', 'delivery_methods')) {
                $table->json('delivery_methods')->nullable()->after('time_needed');
            }
        });
    }

    public function down()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (Schema::hasColumn('messengerial_requests', 'delivery_methods')) {
                $table->dropColumn('delivery_methods');
            }
        });
    }
};
