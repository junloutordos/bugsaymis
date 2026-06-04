<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_contacts', function (Blueprint $table) {
            $table->boolean('notify_sms')->default(false)->after('notify_push');
        });
    }

    public function down(): void
    {
        Schema::table('parent_contacts', function (Blueprint $table) {
            $table->dropColumn('notify_sms');
        });
    }
};
