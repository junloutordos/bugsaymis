<?php

namespace App\Services\HomeroomAttendance;

use App\Models\ClassRecord\ClassRecordAttendanceRecord;
use App\Models\HomeroomAttendance\AdmissionSlip;
use App\Models\HomeroomAttendance\AttendanceRecord;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * School Registrar workflow: a student returning from an absence, tardy, or
 * cut class needs a Class Admission Slip before being let back into class
 * (CIM 7.0 policy 3.7). Issuing the slip records Excused/Unexcused and links
 * back to the originating attendance row(s) so the monthly report picks up
 * the right classification automatically.
 *
 * Cut Class (CIM 3.6/3.6.2) can only be witnessed by the subject teacher —
 * the student is on campus but skips or leaves that specific class period —
 * so it is asserted directly on the Class Record Attendance grid
 * (status = 'cut_class'). As a secondary safety net, in case a teacher marks
 * a student Absent instead of using that status, cutting is also still
 * detected here by cross-referencing a subject's Absent record against the
 * same date's Homeroom whole-day record (Present/Tardy) for that student.
 * Both signals are surfaced to the Registrar, tagged separately so it's
 * clear which is a direct teacher assertion and which is a system-detected
 * mismatch. See SubjectAttendanceSyncService for how subject/homeroom stay
 * in sync in the first place.
 */
class AdmissionSlipService
{
    /** Infractions still awaiting a slip — whole-day (homeroom), teacher-asserted cut class, and detected mismatch, newest first. */
    public function pending(int $sectionId): Collection
    {
        $homeroom = AttendanceRecord::query()
            ->whereIn('status', AttendanceRecord::INFRACTION_STATUSES)
            ->where('excused_status', 'n_a')
            ->whereHas('attendanceDate', fn ($q) => $q->where('section_id', $sectionId))
            ->with(['attendanceDate', 'student'])
            ->get()
            ->map(fn (AttendanceRecord $r) => [
                'type' => 'homeroom',
                'reference_id' => $r->id,
                'student_id' => $r->student_id,
                'student_name' => trim("{$r->student->lastname}, {$r->student->firstname} {$r->student->middlename}"),
                'date' => $r->attendanceDate->date->toDateString(),
                'infraction_type' => $r->status === 'absent' ? 'absence' : $r->status,
            ]);

        return $homeroom
            ->concat($this->pendingAssertedCutClass($sectionId))
            ->concat($this->pendingSuspectedCuttingInstances($sectionId))
            ->sortByDesc('date')
            ->values();
    }

    /** Subject teacher directly marked Cut Class (CIM 3.6/3.6.2) — not yet resolved. */
    private function pendingAssertedCutClass(int $sectionId): Collection
    {
        $rows = DB::table('class_record_attendance_records as car')
            ->join('class_record_attendance_dates as cad', 'cad.id', '=', 'car.class_record_attendance_date_id')
            ->join('class_record_students as crs', 'crs.id', '=', 'car.class_record_student_id')
            ->join('class_record_quarters as crq', 'crq.id', '=', 'crs.class_record_quarter_id')
            ->join('class_records as cr', 'cr.id', '=', 'crq.class_record_id')
            ->where('cr.section_id', $sectionId)
            ->where('car.status', 'cut_class')
            ->where('car.excused_status', 'n_a')
            ->select('car.id as class_record_attendance_record_id', 'crs.student_id', 'cad.date')
            ->distinct()
            ->get();

        return $this->mapCuttingRows($rows, 'cut_class');
    }

    /** Subject Absent + Homeroom Present/Tardy on the same date, not yet resolved — a secondary safety net for a teacher who didn't use the Cut Class status directly. */
    private function pendingSuspectedCuttingInstances(int $sectionId): Collection
    {
        $rows = DB::table('class_record_attendance_records as car')
            ->join('class_record_attendance_dates as cad', 'cad.id', '=', 'car.class_record_attendance_date_id')
            ->join('class_record_students as crs', 'crs.id', '=', 'car.class_record_student_id')
            ->join('homeroom_attendance_dates as had', function ($join) use ($sectionId) {
                $join->on('had.date', '=', 'cad.date')->where('had.section_id', '=', $sectionId);
            })
            ->join('homeroom_attendance_records as har', function ($join) {
                $join->on('har.homeroom_attendance_date_id', '=', 'had.id')
                    ->on('har.student_id', '=', 'crs.student_id');
            })
            ->where('car.status', 'absent')
            ->where('car.excused_status', 'n_a')
            ->whereIn('har.status', ['present', 'tardy'])
            ->select('car.id as class_record_attendance_record_id', 'crs.student_id', 'cad.date')
            ->distinct()
            ->get();

        return $this->mapCuttingRows($rows, 'cutting_suspected');
    }

    /** @param Collection<int, object{class_record_attendance_record_id:int, student_id:int, date:string}> $rows */
    private function mapCuttingRows(Collection $rows, string $infractionType): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $students = Student::whereIn('id', $rows->pluck('student_id')->unique())->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'type' => 'subject_cutting',
            'reference_id' => $row->class_record_attendance_record_id,
            'student_id' => $row->student_id,
            'student_name' => $students->has($row->student_id)
                ? trim("{$students[$row->student_id]->lastname}, {$students[$row->student_id]->firstname} {$students[$row->student_id]->middlename}")
                : 'Unknown Student',
            'date' => $row->date,
            'infraction_type' => $infractionType,
        ]);
    }

    public function issue(array $data, int $issuedByUserId): AdmissionSlip
    {
        return DB::transaction(function () use ($data, $issuedByUserId) {
            $slip = AdmissionSlip::create([
                'student_id' => $data['student_id'],
                'section_id' => $data['section_id'],
                'infraction_date' => $data['infraction_date'],
                'infraction_type' => $data['infraction_type'],
                'excused_status' => $data['excused_status'],
                'reason' => $data['reason'] ?? null,
                'supporting_document_path' => $data['supporting_document_path'] ?? null,
                'issued_by' => $issuedByUserId,
                'issued_at' => now(),
            ]);

            if ($data['infraction_type'] === 'cut_class') {
                // Teacher-asserted — the specific subject row(s) already
                // carry status = 'cut_class' directly, no status remap needed.
                $updated = ClassRecordAttendanceRecord::whereHas(
                    'attendanceDate',
                    fn ($q) => $q->where('date', $data['infraction_date']),
                )
                    ->whereHas('student', fn ($q) => $q->where('student_id', $data['student_id']))
                    ->where('status', 'cut_class')
                    ->where('excused_status', 'n_a')
                    ->update([
                        'excused_status' => $data['excused_status'],
                        'admission_slip_id' => $slip->id,
                    ]);
                abort_if($updated === 0, 422, 'This Cut Class incident has already been resolved.');
            } elseif ($data['infraction_type'] === 'cutting_suspected') {
                // Detected mismatch — only the specific mismatched subject
                // row(s) for this date — the homeroom record correctly stays
                // Present/Tardy.
                $updated = ClassRecordAttendanceRecord::whereHas(
                    'attendanceDate',
                    fn ($q) => $q->where('date', $data['infraction_date']),
                )
                    ->whereHas('student', fn ($q) => $q->where('student_id', $data['student_id']))
                    ->where('status', 'absent')
                    ->where('excused_status', 'n_a')
                    ->update([
                        'excused_status' => $data['excused_status'],
                        'admission_slip_id' => $slip->id,
                    ]);
                abort_if($updated === 0, 422, 'This suspected Cut Class incident has already been resolved.');
            } else {
                $wholeDayStatus = $data['infraction_type'] === 'absence' ? 'absent' : 'tardy';

                AttendanceRecord::whereHas(
                    'attendanceDate',
                    fn ($q) => $q->where('section_id', $data['section_id'])->where('date', $data['infraction_date']),
                )
                    ->where('student_id', $data['student_id'])
                    ->update([
                        'excused_status' => $data['excused_status'],
                        'admission_slip_id' => $slip->id,
                    ]);

                // Carry the same determination through every subject record
                // for that date — they should already mirror this status via
                // SubjectAttendanceSyncService.
                ClassRecordAttendanceRecord::whereHas(
                    'attendanceDate',
                    fn ($q) => $q->where('date', $data['infraction_date']),
                )
                    ->whereHas('student', fn ($q) => $q->where('student_id', $data['student_id']))
                    ->where('status', $wholeDayStatus)
                    ->update([
                        'excused_status' => $data['excused_status'],
                        'admission_slip_id' => $slip->id,
                    ]);
            }

            return $slip;
        });
    }
}
