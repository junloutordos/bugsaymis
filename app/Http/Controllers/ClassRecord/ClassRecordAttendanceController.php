<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAttendanceDate;
use App\Models\ClassRecord\ClassRecordAttendanceRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Services\ClassRecord\ClassRecordMonitorScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassRecordAttendanceController extends Controller
{
    public function __construct(private readonly ClassRecordMonitorScopeService $monitorScope)
    {
    }

    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
    }

    /** Read-only access: admin, the owning teacher, or a scoped monitor (CID Chief / AUH). */
    private function canView(ClassRecord $classRecord): bool
    {
        return $classRecord->canView(Auth::user())
            || $this->monitorScope->canView(Auth::user(), $classRecord);
    }

    private function resolveQuarter(ClassRecord $classRecord, int $q): ClassRecordQuarter
    {
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');
        return ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $q)
            ->firstOrFail();
    }

    /**
     * Resolves and validates the subject_id scope for an attendance request.
     * On a normal (non-shared) record this is always null — nothing to scope.
     * On a shared PEHM record, a non-admin caller must own the requested
     * subject; omitting it defaults to the caller's own subject when they
     * only own one (so existing single-subject-per-teacher UIs keep working
     * without having to pass it explicitly).
     */
    private function resolveSubjectId(ClassRecord $classRecord, ?int $requestedSubjectId): ?int
    {
        if ($classRecord->coTeachers->isEmpty()) {
            return null;
        }

        if ($requestedSubjectId !== null) {
            if (! $this->isAdmin()) {
                abort_unless(
                    $classRecord->canEdit(Auth::user(), $requestedSubjectId),
                    403,
                    'You do not have edit access to that subject on this class record.',
                );
            }

            return $requestedSubjectId;
        }

        // No subject specified — default to the caller's own subject if they
        // own exactly one on this shared record (the common case: a subject
        // teacher's own attendance tab). Admins/monitors with no owned
        // subject fall through to null (all dates, read-only aggregate).
        $ownSubjectIds = $classRecord->coTeachers
            ->where('user_id', Auth::id())
            ->pluck('subject_id')
            ->unique();

        return $ownSubjectIds->count() === 1 ? $ownSubjectIds->first() : null;
    }

    // ── GET /class-records/{cr}/quarters/{q}/attendance ───────────────────────

    public function index(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->canView($classRecord), 403);

        $quarter = $this->resolveQuarter($classRecord, $q);
        $subjectId = $this->resolveSubjectId($classRecord, $request->integer('subject_id') ?: null);

        $dates = ClassRecordAttendanceDate::where('class_record_quarter_id', $quarter->id)
            ->where('subject_id', $subjectId)
            ->orderBy('date')
            ->get(['id', 'date', 'sort_order', 'subject_id']);

        $records = ClassRecordAttendanceRecord::whereHas('attendanceDate', fn ($sq) =>
                $sq->where('class_record_quarter_id', $quarter->id)->where('subject_id', $subjectId)
            )
            ->get(['class_record_attendance_date_id', 'class_record_student_id', 'status', 'excused_status', 'synced_from_homeroom'])
            ->mapWithKeys(fn ($r) => [
                "{$r->class_record_student_id}_{$r->class_record_attendance_date_id}" => [
                    'status'  => $r->status,
                    'excused' => $r->excused_status,
                    'synced'  => $r->synced_from_homeroom,
                ],
            ]);

        return response()->json(['dates' => $dates, 'records' => $records, 'subject_id' => $subjectId]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/attendance/dates ────────────────

    public function storeDates(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        $validated = $request->validate([
            'date'       => 'required|date',
            'subject_id' => 'nullable|integer|exists:subjects,id',
        ]);
        $subjectId = $this->resolveSubjectId($classRecord, $validated['subject_id'] ?? null);
        abort_unless($classRecord->canEdit(Auth::user(), $subjectId), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        $maxOrder = ClassRecordAttendanceDate::where('class_record_quarter_id', $quarter->id)
            ->where('subject_id', $subjectId)
            ->max('sort_order') ?? 0;

        $date = ClassRecordAttendanceDate::firstOrCreate(
            ['class_record_quarter_id' => $quarter->id, 'subject_id' => $subjectId, 'date' => $validated['date']],
            ['sort_order' => $maxOrder + 1]
        );

        return response()->json($date);
    }

    // ── DELETE /class-records/{cr}/quarters/{q}/attendance/dates/{date} ───────

    public function destroyDate(ClassRecord $classRecord, int $q, ClassRecordAttendanceDate $attendanceDate): JsonResponse
    {
        // The date itself already carries the subject scope it belongs to —
        // no need (and no way, for a DELETE with no body) to accept it from
        // the request.
        abort_unless($classRecord->canEdit(Auth::user(), $attendanceDate->subject_id), 403);
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
        $validated = $request->validate([
            'subject_id'           => 'nullable|integer|exists:subjects,id',
            'records'              => 'required|array|min:1',
            'records.*.date_id'    => 'required|integer|exists:class_record_attendance_dates,id',
            'records.*.student_id' => 'required|integer|exists:class_record_students,id',
            // Present/Absent/Tardy/Cut Class. Excused/Unexcused is no longer a
            // status a subject teacher picks; it's set by the Homeroom
            // Adviser/Registrar's Class Admission Slip workflow instead.
            // Uniform checking also moved to the adviser (once per day), so
            // there's no uniform field here anymore.
            // Cut Class (CIM 3.6/3.6.2) can only be witnessed and asserted by
            // the subject teacher — the student was on campus but skipped or
            // left this specific class period — so it's only ever set here,
            // never on the Homeroom whole-day attendance.
            'records.*.status'     => 'nullable|in:present,absent,tardy,cut_class',
        ]);
        $subjectId = $this->resolveSubjectId($classRecord, $validated['subject_id'] ?? null);
        abort_unless($classRecord->canEdit(Auth::user(), $subjectId), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        // "exists" only proves the IDs are valid rows *somewhere* — without this,
        // a teacher could pass a date_id/student_id belonging to another class
        // record (or another subject on this same shared record) entirely and
        // delete or overwrite someone else's attendance data.
        $dateIds = collect($validated['records'])->pluck('date_id')->unique();
        $validDateIds = ClassRecordAttendanceDate::whereIn('id', $dateIds)
            ->where('class_record_quarter_id', $quarter->id)
            ->where('subject_id', $subjectId)
            ->pluck('id');
        abort_if(
            $validDateIds->count() !== $dateIds->count(),
            422,
            $subjectId === null
                ? 'One or more dates do not belong to this quarter.'
                : 'One or more dates do not belong to this quarter/subject.',
        );

        $studentIds = collect($validated['records'])->pluck('student_id')->unique();
        $validStudentIds = ClassRecordStudent::whereIn('id', $studentIds)
            ->where('class_record_quarter_id', $quarter->id)
            ->pluck('id');
        abort_if($validStudentIds->count() !== $studentIds->count(), 422, 'One or more students do not belong to this class record.');

        $now      = now();
        $toUpsert = [];

        foreach ($validated['records'] as $item) {
            $status = $item['status'] ?? null;

            // A cell with no status has no reason to exist as a row.
            if ($status === null) {
                DB::table('class_record_attendance_records')
                    ->where('class_record_attendance_date_id', $item['date_id'])
                    ->where('class_record_student_id', $item['student_id'])
                    ->delete();
            } else {
                $toUpsert[] = [
                    'class_record_attendance_date_id' => $item['date_id'],
                    'class_record_student_id'         => $item['student_id'],
                    'status'                          => $status,
                    // A manual save from the subject teacher is always an
                    // explicit override — clear this so a later Homeroom
                    // Attendance resync never clobbers it again (see
                    // SubjectAttendanceSyncService).
                    'synced_from_homeroom'            => false,
                    'created_at'                      => $now,
                    'updated_at'                      => $now,
                ];
            }
        }

        if ($toUpsert) {
            DB::table('class_record_attendance_records')->upsert(
                $toUpsert,
                ['class_record_attendance_date_id', 'class_record_student_id'],
                ['status', 'synced_from_homeroom', 'updated_at']
            );
        }

        return response()->json(['message' => count($toUpsert) . ' record(s) saved.']);
    }
}
