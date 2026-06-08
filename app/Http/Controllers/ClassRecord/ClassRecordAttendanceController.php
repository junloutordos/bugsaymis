<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAttendanceDate;
use App\Models\ClassRecord\ClassRecordAttendanceRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassRecordAttendanceController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
    }

    private function resolveQuarter(ClassRecord $classRecord, int $q): ClassRecordQuarter
    {
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');
        return ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $q)
            ->firstOrFail();
    }

    // ── GET /class-records/{cr}/quarters/{q}/attendance ───────────────────────

    public function index(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $quarter = $this->resolveQuarter($classRecord, $q);

        $dates = ClassRecordAttendanceDate::where('class_record_quarter_id', $quarter->id)
            ->orderBy('date')
            ->get(['id', 'date', 'sort_order']);

        $records = ClassRecordAttendanceRecord::whereHas('attendanceDate', fn ($sq) =>
                $sq->where('class_record_quarter_id', $quarter->id)
            )
            ->get(['class_record_attendance_date_id', 'class_record_student_id', 'status'])
            ->mapWithKeys(fn ($r) =>
                ["{$r->class_record_student_id}_{$r->class_record_attendance_date_id}" => $r->status]
            );

        return response()->json(['dates' => $dates, 'records' => $records]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/attendance/dates ────────────────

    public function storeDates(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $maxOrder = ClassRecordAttendanceDate::where('class_record_quarter_id', $quarter->id)
            ->max('sort_order') ?? 0;

        $date = ClassRecordAttendanceDate::firstOrCreate(
            ['class_record_quarter_id' => $quarter->id, 'date' => $validated['date']],
            ['sort_order' => $maxOrder + 1]
        );

        return response()->json($date);
    }

    // ── DELETE /class-records/{cr}/quarters/{q}/attendance/dates/{date} ───────

    public function destroyDate(ClassRecord $classRecord, int $q, ClassRecordAttendanceDate $attendanceDate): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_unless($attendanceDate->class_record_quarter_id === $quarter->id, 404);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        $attendanceDate->delete();

        return response()->json(['message' => 'Date removed.']);
    }

    // ── POST /class-records/{cr}/quarters/{q}/attendance/records ─────────────

    public function upsert(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'records'              => 'required|array|min:1',
            'records.*.date_id'    => 'required|integer|exists:class_record_attendance_dates,id',
            'records.*.student_id' => 'required|integer|exists:class_record_students,id',
            'records.*.status'     => 'nullable|in:present,absent,late,excused',
        ]);

        $now      = now();
        $toUpsert = [];

        foreach ($validated['records'] as $item) {
            if ($item['status'] === null) {
                DB::table('class_record_attendance_records')
                    ->where('class_record_attendance_date_id', $item['date_id'])
                    ->where('class_record_student_id', $item['student_id'])
                    ->delete();
            } else {
                $toUpsert[] = [
                    'class_record_attendance_date_id' => $item['date_id'],
                    'class_record_student_id'         => $item['student_id'],
                    'status'                          => $item['status'],
                    'created_at'                      => $now,
                    'updated_at'                      => $now,
                ];
            }
        }

        if ($toUpsert) {
            DB::table('class_record_attendance_records')->upsert(
                $toUpsert,
                ['class_record_attendance_date_id', 'class_record_student_id'],
                ['status', 'updated_at']
            );
        }

        return response()->json(['message' => count($toUpsert) . ' record(s) saved.']);
    }
}
