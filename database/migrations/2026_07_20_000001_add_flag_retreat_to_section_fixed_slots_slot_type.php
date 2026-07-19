<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SchedulingConstants::getBlockedSlots()/getDisplayBlockedSlots() tag the
 * Friday Flag Retreat block with type 'FLAG_RETREAT' (SchedulingConstants.php
 * lines 847, 918), but section_fixed_slots.slot_type's enum was never
 * updated to accept it when that feature was built — FixedSlotService::
 * sealFixedSlots() throws a truncation error for every grade the moment it
 * tries to insert that block, since Friday Flag Retreat applies to all
 * grades (SchedulingConstants::FRIDAY_FLAG_RETREAT).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE section_fixed_slots MODIFY COLUMN slot_type ENUM(
            'FLAG', 'HOMEROOM', 'ADVISING', 'DEAD',
            'RECESS', 'LUNCH', 'CONSULT',
            'WELLNESS', 'ACTIVITY', 'ILA', 'FLAG_RETREAT'
        )");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE section_fixed_slots MODIFY COLUMN slot_type ENUM(
            'FLAG', 'HOMEROOM', 'ADVISING', 'DEAD',
            'RECESS', 'LUNCH', 'CONSULT',
            'WELLNESS', 'ACTIVITY', 'ILA'
        )");
    }
};
