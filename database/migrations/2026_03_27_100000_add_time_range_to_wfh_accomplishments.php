<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wfh_accomplishments', function (Blueprint $table) {
            $table->time('time_from')->nullable()->after('description');
            $table->time('time_to')->nullable()->after('time_from');
        });
    }

    public function down(): void
    {
        Schema::table('wfh_accomplishments', function (Blueprint $table) {
            $table->dropColumn(['time_from', 'time_to']);
        });
    }
};
