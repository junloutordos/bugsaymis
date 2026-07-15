<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pds', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('user_id');
            $table->timestamp('annual_reminder_sent_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('pds', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'annual_reminder_sent_at']);
        });
    }
};
