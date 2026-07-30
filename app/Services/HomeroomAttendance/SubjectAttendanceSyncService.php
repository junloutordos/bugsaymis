<?php

namespace App\Services\HomeroomAttendance;

use App\Models\ClassRecord\ClassRecordAttendanceDate;
use App\Models\ClassRecord\ClassRecordAttendanceRecord;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\HomeroomAttendance\AttendanceDate;
use App\Models\HomeroomAttendance\AttendanceRecord;
use App\Models\Registrar\StudentEnrollment;
use App\Services\SchoolCalendarService;
use Carbon\Carbon;

/**
 * Schedule-driven auto-creation: when the Homeroom Adviser saves a student's
 * whole-day status, push it into every subject Class Record the student is
 * actually scheduled to meet that day — so subject teachers see the day
 * already pre-filled and only need to correct exceptions.
 *
 * A subject-level cell a teacher has already touched manually
 * (synced_from_homeroom = false) is never overwritten — that's exactly the
 * mismatch AdmissionSlipService uses to detect cutting, so clobbering it
 * would erase the one thing this whole mechanism is meant to surface.
 */
class SubjectAttendanceSyncService
{
    public function __construct(private SchoolCalendarService $calendar)
    {
    }

    public function syncForStudent(int $studentId, string $date, string $status): void
    {
        $dayOfWeek = Carbon::parse($date)->format('l');

        $classRecordStudents = ClassRecordStudent::where('student_id', $studentId)
            ->where('is_active', true)
            ->whereHas('quarter', fn ($q) => $q->where('is_locked', false))
            ->with(['quarter.classRecord.section', 'quarter.classRecord.schoolYear'])
            ->get();

        foreach ($classRecordStudents as $classRecordStudent) {
            $quarter = $classRecordStudent->quarter;
            $classRecord = $quarter?->classRecord;

            if (! $classRecord || ! $classRecord->isCurrentSchoolYear()) {
                continue;
            }

            // A shared PEHM record has one independent schedule per subject
            // (PE/Health/Music each meet on their own days) — sync into
            // whichever of them actually meet today, each under its own
            // subject-scoped attendance date. A normal record just has its
            // single subject_id, same as before.
            $classRecord->loadMissing('coTeachers');
            $subjectIds = $classRecord->coTeachers->isNotEmpty()
                ? $classRecord->coTeachers->pluck('subject_id')->unique()->filter()->values()
                : collect([$classRecord->subject_id])->filter()->values();

            foreach ($subjectIds as $subjectId) {
                $this->syncSubjectForDay($classRecord, $quarter, $classRecordStudent, $subjectId, $dayOfWeek, $date, $status);
            }
        }
    }

    private function syncSubjectForDay($classRecord, $quarter, $classRecordStudent, int $subjectId, string $dayOfWeek, string $date, string $status): void
    {
        $meetsToday = ClassSchedule::classes()
            ->where('section_id', $classRecord->section_id)
            ->where('subject_id', $subjectId)
            ->onDay($dayOfWeek)
            ->exists();

        if (! $meetsToday) {
            return;
        }

        if (! $this->calendar->isSchoolDay($date, $classRecord->section?->levelid)) {
            return;
        }

        // subject_id is null here for a normal (non-shared) record — same
        // scope as every date that controller/service already writes for it.
        $scopedSubjectId = $classRecord->coTeachers->isNotEmpty() ? $subjectId : null;

        $maxOrder = ClassRecordAttendanceDate::where('class_record_quarter_id', $quarter->id)
            ->where('subject_id', $scopedSubjectId)
            ->max('sort_order') ?? 0;
        $attendanceDate = ClassRecordAttendanceDate::firstOrCreate(
            ['class_record_quarter_id' => $quarter->id, 'subject_id' => $scopedSubjectId, 'date' => $date],
            ['sort_order' => $maxOrder + 1],
        );

        $existing = ClassRecordAttendanceRecord::where('class_record_attendance_date_id', $attendanceDate->id)
            ->where('class_record_student_id', $classRecordStudent->id)
            ->first();

        if (! $existing) {
            ClassRecordAttendanceRecord::create([
                'class_record_attendance_date_id' => $attendanceDate->id,
                'class_record_student_id'         => $classRecordStudent->id,
                'status'                           => $status,
                'synced_from_homeroom'             => true,
            ]);
        } elseif ($existing->synced_from_homeroom) {
            $existing->update(['status' => $status]);
        }
        // else: a teacher has already touched this cell — leave it alone,
        // the mismatch (if any) is exactly what surfaces as cutting.
    }

    /**
     * Pushes a subject teacher's "Incomplete Uniform" flag into Homeroom's
     * own `incomplete_uniform` column for the same student/date — creating
     * the Homeroom AttendanceDate/AttendanceRecord for that day if the
     * adviser hasn't logged it yet (defaulting status to 'present', the
     * same implicit default the adviser's own UI already assumes, and
     * `taken_by` to the subject teacher who triggered the sync — the
     * adviser overwrites this the moment they actually take attendance
     * that day via DailyAttendanceService::saveDay()).
     *
     * One-directional and additive only: this only ever sets the flag to
     * true, never clears a box the adviser (or an earlier sync) already
     * checked. Homeroom's status vocabulary (present/absent/tardy) is
     * never touched by this method — IU stays a flag, not a status, on
     * both sides of the sync.
     */
    public function syncIncompleteUniform(int $studentId, string $date, int $triggeredByUserId): void
    {
        $enrollment = StudentEnrollment::active()
            ->where('student_id', $studentId)
            ->forSchoolYear(SchoolYear::where('is_current', true)->value('id'))
            ->first();

        if (! $enrollment) {
            return;
        }

        $attendanceDate = AttendanceDate::firstOrCreate(
            ['section_id' => $enrollment->section_id, 'date' => $date],
            ['school_year_id' => $enrollment->school_year_id, 'taken_by' => $triggeredByUserId],
        );

        $existing = AttendanceRecord::where('homeroom_attendance_date_id', $attendanceDate->id)
            ->where('student_id', $studentId)
            ->first();

        if (! $existing) {
            AttendanceRecord::create([
                'homeroom_attendance_date_id' => $attendanceDate->id,
                'student_id'                  => $studentId,
                'status'                      => 'present',
                'incomplete_uniform'          => true,
            ]);
        } elseif (! $existing->incomplete_uniform) {
            $existing->update(['incomplete_uniform' => true]);
        }
        // else: already flagged (by the adviser or an earlier sync) — leave it alone.
    }
}
