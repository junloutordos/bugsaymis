<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PEHM shared class records need one attendance calendar PER SUBJECT (PE,
 * Health, and Music each meet on different days and are marked separately),
 * even though they share one roster and one class record. Null (the default,
 * and every pre-existing row) means "not subject-split" — a normal
 * single-teacher record's attendance is unaffected.
 *
 * The old unique constraint (quarter, date) is replaced with
 * (quarter, subject_id, date) so the same calendar date can exist once per
 * subject on a shared record, while still being unique per subject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_record_attendance_dates', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('class_record_quarter_id')
                ->constrained('subjects')->nullOnDelete();

            // Add the new unique index before dropping the old one — MySQL
            // refuses to drop attendance_date_unique while it's the only
            // index backing the class_record_quarter_id FK constraint.
            $table->unique(
                ['class_record_quarter_id', 'subject_id', 'date'],
                'attendance_date_subject_unique'
            );
        });

        Schema::table('class_record_attendance_dates', function (Blueprint $table) {
            $table->dropUnique('attendance_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('class_record_attendance_dates', function (Blueprint $table) {
            $table->unique(['class_record_quarter_id', 'date'], 'attendance_date_unique');
        });

        Schema::table('class_record_attendance_dates', function (Blueprint $table) {
            $table->dropUnique('attendance_date_subject_unique');
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
