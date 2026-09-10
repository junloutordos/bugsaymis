<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets the catalog's member picker capture a per-member committee role
     * (member|secretary|co_chair) without a separate per-term assignment
     * form. Chairperson stays driven by committees.head_id, unchanged.
     * CommitteeRosterService::reconcileOne() reads this to stop collapsing
     * every non-head member into a plain 'member' assignment.
     */
    public function up(): void
    {
        Schema::table('committee_user', function (Blueprint $table) {
            $table->string('role', 20)->default('member')->after('task')
                  ->comment('member|secretary|co_chair — chairperson is derived from committees.head_id');
            $table->decimal('load_units_override', 4, 2)->nullable()->after('role')
                  ->comment('Per-member load-unit override; null = use the committee role default');
        });
    }

    public function down(): void
    {
        Schema::table('committee_user', function (Blueprint $table) {
            $table->dropColumn(['role', 'load_units_override']);
        });
    }
};
