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

            $meetsToday = ClassSchedule::classes()
                ->where('section_id', $classRecord->section_id)
                ->where('subject_id', $classRecord->subject_id)
                ->onDay($dayOfWeek)
                ->exists();

            if (! $meetsToday) {
                continue;
            }

            if (! $this->calendar->isSchoolDay($date, $classRecord->section?->levelid)) {
                continue;
            }

            $maxOrder = ClassRecordAttendanceDate::where('class_record_quarter_id', $quarter->id)->max('sort_order') ?? 0;
            $attendanceDate = ClassRecordAttendanceDate::firstOrCreate(
                ['class_record_quarter_id' => $quarter->id, 'date' => $date],
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
}
