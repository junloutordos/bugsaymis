<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_credentials', function (Blueprint $table) {
            $table->string('fcm_device_token', 500)->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_credentials', function (Blueprint $table) {
            $table->dropColumn('fcm_device_token');
        });
    }
};
