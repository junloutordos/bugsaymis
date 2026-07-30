<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 'cut_class' already exists in the status enum from the prior expand
     * migration, but that migration immediately remapped every legacy
     * 'cut_class' row to 'absent' and no app code path has written it since
     * — cutting was purely a derived/detected condition, never a status a
     * subject teacher picked directly.
     *
     * Per CIM 3.6/3.6.2, cutting can only be witnessed by the subject
     * teacher (student is on campus but skips/leaves the specific class
     * period) — the Homeroom Adviser has no way to observe this. This
     * migration re-activates 'cut_class' as a value app code writes again,
     * scoped to class_record_attendance_records only. No schema change is
     * needed — the enum value is already there — so this is a no-op
     * migration that exists purely to document the re-activation for
     * anyone reading migration history. homeroom_attendance_records.status
     * is intentionally left untouched — the adviser never sets this.
     */
    public function up(): void
    {
        // No-op: 'cut_class' is already a valid enum value on this column.
    }

    public function down(): void
    {
        // No-op: nothing to revert (the enum value predates this migration).
    }
};
