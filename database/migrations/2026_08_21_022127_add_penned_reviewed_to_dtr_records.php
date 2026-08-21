<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            // Set by HR when they approve an employee's submitted penned entries
            // for the month. Cleared again by unlockPenned() if HR reopens the
            // submission for correction — an approval only stands until the
            // next unlock.
            $table->timestamp('penned_reviewed_at')->nullable()->after('penned_submitted_by');
            $table->unsignedBigInteger('penned_reviewed_by')->nullable()->after('penned_reviewed_at');
            $table->foreign('penned_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->dropForeign(['penned_reviewed_by']);
            $table->dropColumn(['penned_reviewed_at', 'penned_reviewed_by']);
        });
    }
};
