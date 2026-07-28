<?php

namespace App\Services\HomeroomAttendance;

use App\Models\HomeroomAttendance\AdmissionSlip;
use App\Models\HomeroomAttendance\AttendanceRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * School Registrar workflow: a student returning from an absence, tardy, or
 * cut class needs a Class Admission Slip before being let back into class
 * (CIM 7.0 policy 3.7). Issuing the slip records Excused/Unexcused and links
 * back to the originating homeroom_attendance_records row so the monthly
 * report picks up the right classification automatically.
 */
class AdmissionSlipService
{
    /** Infractions still awaiting a slip (status set but no excused/unexcused determination yet). */
    public function pending(int $sectionId): Collection
    {
        return AttendanceRecord::query()
            ->whereIn('status', AttendanceRecord::INFRACTION_STATUSES)
            ->where('excused_status', 'n_a')
            ->whereHas('attendanceDate', fn ($q) => $q->where('section_id', $sectionId))
            ->with(['attendanceDate', 'student'])
            ->get()
            ->sortByDesc(fn (AttendanceRecord $r) => $r->attendanceDate->date)
            ->values();
    }

    public function issue(array $data, int $issuedByUserId): AdmissionSlip
    {
        return DB::transaction(function () use ($data, $issuedByUserId) {
            $slip = AdmissionSlip::create([
                'student_id'                => $data['student_id'],
                'section_id'                => $data['section_id'],
                'infraction_date'           => $data['infraction_date'],
                'infraction_type'           => $data['infraction_type'],
                'excused_status'            => $data['excused_status'],
                'reason'                    => $data['reason'] ?? null,
                'supporting_document_path'  => $data['supporting_document_path'] ?? null,
                'issued_by'                 => $issuedByUserId,
                'issued_at'                 => now(),
            ]);

            AttendanceRecord::whereHas(
                'attendanceDate',
                fn ($q) => $q->where('section_id', $data['section_id'])->where('date', $data['infraction_date']),
            )
                ->where('student_id', $data['student_id'])
                ->update([
                    'excused_status'    => $data['excused_status'],
                    'admission_slip_id' => $slip->id,
                ]);

            return $slip;
        });
    }
}
