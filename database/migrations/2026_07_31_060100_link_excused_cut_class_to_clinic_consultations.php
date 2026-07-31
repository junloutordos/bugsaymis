<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for ECC (Excused Cutting Class).
     *
     * The attendance fact remains status=cut_class. Its resolution is stored
     * in the existing excused_status column, while these nullable columns
     * identify the validating clinic record, teacher, and confirmation time.
     *
     * Additive-only — safe for blue-green deployment.
     */
    public function up(): void
    {
        Schema::table('class_record_attendance_records', function (Blueprint $table) {
            $table->foreignId('clinic_consultation_id')
                ->nullable()
                ->after('admission_slip_id')
                ->constrained('consultations')
                ->nullOnDelete();
            $table->foreignId('excused_by')
                ->nullable()
                ->after('clinic_consultation_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('excused_at')->nullable()->after('excused_by');
        });
    }

    public function down(): void
    {
        Schema::table('class_record_attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_consultation_id');
            $table->dropConstrainedForeignId('excused_by');
            $table->dropColumn('excused_at');
        });
    }
};
