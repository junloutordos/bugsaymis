<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Committee lifecycle/scope model:
     *   scope_type   — perpetual (default, active until explicitly revoked),
     *                  school_year (auto-inactive after its tagged SY),
     *                  seasonal (explicit season_starts_at/season_ends_at window,
     *                  e.g. Foundation Week / Graduation committees).
     *   revoked_at/revoked_by/revocation_reason — soft lifecycle end, distinct
     *   from is_active (a quick toggle) — revocation is a deliberate,
     *   auditable act with a reason, never a plain delete.
     *   amended_from_committee_id — amending a committee creates a new
     *   versioned row rather than mutating history; this links forward from
     *   the OLD row to its replacement.
     */
    public function up(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            if (! Schema::hasColumn('committees', 'scope_type')) {
                $table->string('scope_type', 20)->default('perpetual')->after('parent_committee_id')
                      ->comment('perpetual|school_year|seasonal');
            }
            if (! Schema::hasColumn('committees', 'school_year_id')) {
                $table->foreignId('school_year_id')->nullable()->after('scope_type')
                      ->constrained('school_years')->nullOnDelete()
                      ->comment('Required when scope_type=school_year');
            }
            if (! Schema::hasColumn('committees', 'season_starts_at')) {
                $table->date('season_starts_at')->nullable()->after('school_year_id')
                      ->comment('Required when scope_type=seasonal');
            }
            if (! Schema::hasColumn('committees', 'season_ends_at')) {
                $table->date('season_ends_at')->nullable()->after('season_starts_at')
                      ->comment('Required when scope_type=seasonal');
            }
            if (! Schema::hasColumn('committees', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('season_ends_at');
            }
            if (! Schema::hasColumn('committees', 'revoked_by')) {
                $table->foreignId('revoked_by')->nullable()->after('revoked_at')
                      ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('committees', 'revocation_reason')) {
                $table->text('revocation_reason')->nullable()->after('revoked_by');
            }
            if (! Schema::hasColumn('committees', 'amended_from_committee_id')) {
                $table->foreignId('amended_from_committee_id')->nullable()->after('revocation_reason')
                      ->constrained('committees')->nullOnDelete()
                      ->comment('Points from the NEW row back to the OLD row it amends/replaces');
            }
        });
    }

    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('amended_from_committee_id');
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn('revocation_reason');
            $table->dropColumn('revoked_at');
            $table->dropColumn('season_ends_at');
            $table->dropColumn('season_starts_at');
            $table->dropConstrainedForeignId('school_year_id');
            $table->dropColumn('scope_type');
        });
    }
};
