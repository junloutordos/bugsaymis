<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            $table->string('proof_of_delivery')->nullable()->after('declined_at');
            $table->timestamp('completed_at')->nullable()->after('proof_of_delivery');
        });
    }

    public function down()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            $table->dropColumn(['proof_of_delivery', 'completed_at']);
        });
    }
};
