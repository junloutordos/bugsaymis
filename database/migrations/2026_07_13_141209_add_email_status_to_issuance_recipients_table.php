<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('issuance_recipients', function (Blueprint $table) {
            $table->string('email_status', 20)->default('pending')->after('office_id'); // pending | sent | failed | skipped
            $table->timestamp('emailed_at')->nullable()->after('email_status');
            $table->text('email_error')->nullable()->after('emailed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issuance_recipients', function (Blueprint $table) {
            $table->dropColumn(['email_status', 'emailed_at', 'email_error']);
        });
    }
};
