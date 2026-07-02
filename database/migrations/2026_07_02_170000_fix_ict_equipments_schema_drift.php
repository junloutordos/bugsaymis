<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Production's ict_equipments predates the tracked create migration and
     * drifted from it: owner_id/status are NOT NULL and several varchars are
     * narrower (50/100/255) than the migration defines. The NOT NULL owner_id
     * broke Atlas Sentinel enrollment for cloned/generic desktops — the
     * "Pending Setup" draft insert has no owner and 500s under strict mode.
     * Converge every drifted column to the dev-canonical shape; each MODIFY
     * is a no-op where the column already matches. serial_no stays nullable
     * (unlike the create migration) because production has existing rows
     * without one.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ict_equipments')) {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE ict_equipments
                MODIFY category VARCHAR(255) NOT NULL,
                MODIFY owner_id BIGINT UNSIGNED NULL,
                MODIFY property_no VARCHAR(255) NULL,
                MODIFY serial_no VARCHAR(255) NULL,
                MODIFY description TEXT NOT NULL,
                MODIFY amount DECIMAL(12, 2) NULL,
                MODIFY status VARCHAR(255) NULL,
                MODIFY remarks TEXT NULL
        SQL);
    }

    /**
     * Reverts to the shape the create migration defines (not production's
     * drifted shape — restoring NOT NULL owner_id would re-break enrollment
     * and can fail outright once null-owner rows exist).
     */
    public function down(): void
    {
        if (! Schema::hasTable('ict_equipments')) {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE ict_equipments
                MODIFY category VARCHAR(255) NOT NULL,
                MODIFY owner_id BIGINT UNSIGNED NULL,
                MODIFY property_no VARCHAR(255) NULL,
                MODIFY serial_no VARCHAR(255) NULL,
                MODIFY description TEXT NOT NULL,
                MODIFY amount DECIMAL(12, 2) NULL,
                MODIFY status VARCHAR(255) NULL,
                MODIFY remarks TEXT NULL
        SQL);
    }
};
