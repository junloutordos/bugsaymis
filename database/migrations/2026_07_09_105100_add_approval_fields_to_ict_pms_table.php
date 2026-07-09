<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ict_pms', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('performed_by')
                ->constrained('users')->nullOnDelete();
            $table->string('approval_status')->default('pending')->after('status');
            $table->foreignId('approved_by')->nullable()->after('approval_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('declined_reason')->nullable()->after('approved_at');
        });

        // Backfill: performed_by reflected the creator at the time each row was
        // originally created (before update() started overwriting it on edits).
        // Pre-existing rows predate this feature — treat them as already approved
        // so OCD's inbox only surfaces schedules created going forward.
        DB::table('ict_pms')->update([
            'created_by'      => DB::raw('performed_by'),
            'approval_status' => 'approved',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ict_pms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('approval_status');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropColumn('declined_reason');
        });
    }
};
