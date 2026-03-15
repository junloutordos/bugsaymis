<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('messengerial_requests', 'consignee_name')) {
                $table->string('consignee_name')->nullable()->after('reference_no');
            }
            if (! Schema::hasColumn('messengerial_requests', 'consignee_contact')) {
                $table->string('consignee_contact')->nullable()->after('consignee_name');
            }
            if (! Schema::hasColumn('messengerial_requests', 'consignee_email')) {
                $table->string('consignee_email')->nullable()->after('consignee_contact');
            }
        });
    }

    public function down()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (Schema::hasColumn('messengerial_requests', 'consignee_email')) {
                $table->dropColumn('consignee_email');
            }
            if (Schema::hasColumn('messengerial_requests', 'consignee_contact')) {
                $table->dropColumn('consignee_contact');
            }
            if (Schema::hasColumn('messengerial_requests', 'consignee_name')) {
                $table->dropColumn('consignee_name');
            }
        });
    }
};
