<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a 'walkin' participant_type so non-registered (QR walk-in) attendees
 * can submit an evaluation without a real ActivityParticipant row. Since
 * walk-ins have no real row to point at, participant_id becomes nullable —
 * MySQL's unique index treats each NULL as distinct, so the existing
 * per-participant unique constraints continue to work unchanged and every
 * walk-in row is naturally exempt from the per-participant duplicate check.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ams_activity_evaluations MODIFY COLUMN participant_type ENUM('employee','student','walkin') NOT NULL");
        DB::statement("ALTER TABLE ams_activity_tws_evaluations MODIFY COLUMN participant_type ENUM('employee','student','walkin') NOT NULL");
        DB::statement("ALTER TABLE ams_activity_speaker_evaluations MODIFY COLUMN participant_type ENUM('employee','student','walkin') NOT NULL");

        Schema::table('ams_activity_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable()->change();
        });
        Schema::table('ams_activity_tws_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable()->change();
        });
        Schema::table('ams_activity_speaker_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Walk-in rows (participant_type='walkin' or null participant_id) must be
        // removed before reverting — they can't satisfy the original NOT NULL
        // enum/column constraints.
        DB::table('ams_activity_evaluations')->where('participant_type', 'walkin')->orWhereNull('participant_id')->delete();
        DB::table('ams_activity_tws_evaluations')->where('participant_type', 'walkin')->orWhereNull('participant_id')->delete();
        DB::table('ams_activity_speaker_evaluations')->where('participant_type', 'walkin')->orWhereNull('participant_id')->delete();

        Schema::table('ams_activity_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable(false)->change();
        });
        Schema::table('ams_activity_tws_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable(false)->change();
        });
        Schema::table('ams_activity_speaker_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable(false)->change();
        });

        DB::statement("ALTER TABLE ams_activity_evaluations MODIFY COLUMN participant_type ENUM('employee','student') NOT NULL");
        DB::statement("ALTER TABLE ams_activity_tws_evaluations MODIFY COLUMN participant_type ENUM('employee','student') NOT NULL");
        DB::statement("ALTER TABLE ams_activity_speaker_evaluations MODIFY COLUMN participant_type ENUM('employee','student') NOT NULL");
    }
};
