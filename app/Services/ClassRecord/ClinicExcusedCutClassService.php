<?php

namespace App\Services\ClassRecord;

use App\Models\ClassRecord\ClassRecordAttendanceRecord;
use App\Models\Consultation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClinicExcusedCutClassService
{
    /**
     * Completed clinic visits that can validate this exact student's CC row.
     *
     * The caller decides which privacy-safe fields to expose. Medical details
     * (complaint, vitals, medicine, and treatment) never leave this service.
     */
    public function candidates(ClassRecordAttendanceRecord $record): Collection
    {
        $record->loadMissing(['attendanceDate', 'student']);

        $studentId = $record->student?->student_id;
        if (! $studentId) {
            return new Collection;
        }

        return Consultation::query()
            ->with('nurse:id,name')
            ->where('student_id', $studentId)
            ->whereRaw('LOWER(status) = ?', ['completed'])
            ->whereDate('date_attended', $record->attendanceDate->date->toDateString())
            ->orderByDesc('date_attended')
            ->get();
    }

    public function excuse(
        ClassRecordAttendanceRecord $record,
        int $consultationId,
        int $teacherId,
    ): ClassRecordAttendanceRecord {
        return DB::transaction(function () use ($record, $consultationId, $teacherId) {
            $lockedRecord = ClassRecordAttendanceRecord::query()
                ->with(['attendanceDate', 'student'])
                ->lockForUpdate()
                ->findOrFail($record->id);

            abort_unless($lockedRecord->status === 'cut_class', 422, 'Only a Cut Class record can be marked ECC.');
            abort_unless($lockedRecord->excused_status === 'n_a', 422, 'This Cut Class record has already been resolved.');
            abort_unless($lockedRecord->admission_slip_id === null, 422, 'This Cut Class record already has a Registrar admission slip.');

            $consultation = Consultation::query()
                ->lockForUpdate()
                ->findOrFail($consultationId);

            $studentId = $lockedRecord->student?->student_id;
            $attendanceDate = $lockedRecord->attendanceDate->date->toDateString();
            $consultationDate = $consultation->date_attended?->toDateString();

            abort_unless(
                $studentId
                && (int) $consultation->student_id === (int) $studentId
                && strtolower((string) $consultation->status) === 'completed'
                && $consultationDate === $attendanceDate,
                422,
                'The selected clinic consultation does not match this student and attendance date.',
            );

            $lockedRecord->update([
                'excused_status' => 'excused',
                'clinic_consultation_id' => $consultation->id,
                'excused_by' => $teacherId,
                'excused_at' => now(),
            ]);

            return $lockedRecord->refresh();
        });
    }
}
