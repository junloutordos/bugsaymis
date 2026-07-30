<?php

namespace App\Services\HomeroomAttendance;

use App\Models\ClassRecord\ClassRecordAttendanceDate;
use App\Models\ClassRecord\ClassRecordAttendanceRecord;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\FacultyLoading\ClassSchedule;
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
}
