<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('messengerial_requests', 'messengerial_kinds')) {
                $table->json('messengerial_kinds')->nullable()->after('delivery_methods');
            }
        });
    }

    public function down()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (Schema::hasColumn('messengerial_requests', 'messengerial_kinds')) {
                $table->dropColumn('messengerial_kinds');
            }
        });
    }
};
