<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_clearance_items', function (Blueprint $table) {
            $table->text('blocker_summary')->nullable()->after('accountability');
            $table->json('blocker_metadata')->nullable()->after('blocker_summary');
            $table->timestamp('blocker_checked_at')->nullable()->after('blocker_metadata');
        });
    }

    public function down(): void
    {
        Schema::table('student_clearance_items', function (Blueprint $table) {
            $table->dropColumn(['blocker_summary', 'blocker_metadata', 'blocker_checked_at']);
        });
    }
};
