<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordIlaDate;
use App\Models\ClassRecord\ClassRecordIlaRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Services\ClassRecord\ClassRecordMonitorScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassRecordIlaController extends Controller
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
        return $this->isAdmin()
            || $classRecord->teacher_id === Auth::id()
            || $this->monitorScope->canView(Auth::user(), $classRecord);
    }

    private function resolveQuarter(ClassRecord $classRecord, int $q): ClassRecordQuarter
    {
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');

        return ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $q)
            ->firstOrFail();
    }

    // ── GET /class-records/{cr}/quarters/{q}/ila ──────────────────────────────

    public function index(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->canView($classRecord), 403);

        $quarter = $this->resolveQuarter($classRecord, $q);

        $dates = ClassRecordIlaDate::where('class_record_quarter_id', $quarter->id)
            ->orderBy('date')
            ->get(['id', 'date', 'is_auto_generated', 'sort_order']);

        $records = ClassRecordIlaRecord::whereHas('ilaDate', fn ($sq) =>
                $sq->where('class_record_quarter_id', $quarter->id)
            )
            ->get(['class_record_ila_date_id', 'class_record_student_id', 'status'])
            ->mapWithKeys(fn ($r) => [
                "{$r->class_record_student_id}_{$r->class_record_ila_date_id}" => $r->status,
            ]);

        return response()->json(['dates' => $dates, 'records' => $records]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/ila/dates ───────────────────────

    public function storeDate(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $maxOrder = ClassRecordIlaDate::where('class_record_quarter_id', $quarter->id)
            ->max('sort_order') ?? 0;

        $date = ClassRecordIlaDate::firstOrCreate(
            ['class_record_quarter_id' => $quarter->id, 'date' => $validated['date']],
            ['sort_order' => $maxOrder + 1, 'is_auto_generated' => false]
        );

        return response()->json($date);
    }

    // ── DELETE /class-records/{cr}/quarters/{q}/ila/dates/{ilaDate} ───────────

    public function destroyDate(ClassRecord $classRecord, int $q, ClassRecordIlaDate $ilaDate): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_unless($ilaDate->class_record_quarter_id === $quarter->id, 404);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        $ilaDate->delete();

        return response()->json(['message' => 'Date removed.']);
    }

    // ── POST /class-records/{cr}/quarters/{q}/ila/records ─────────────────────

    public function upsert(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'records'              => 'required|array|min:1',
            'records.*.date_id'    => 'required|integer|exists:class_record_ila_dates,id',
            'records.*.student_id' => 'required|integer|exists:class_record_students,id',
            'records.*.status'     => 'nullable|in:compliant,non_compliant',
        ]);

        // "exists" only proves the IDs are valid rows *somewhere* — without this,
        // a teacher could pass a date_id/student_id belonging to another class
        // record entirely and delete or overwrite someone else's ILA data.
        $dateIds = collect($validated['records'])->pluck('date_id')->unique();
        $validDateIds = ClassRecordIlaDate::whereIn('id', $dateIds)
            ->where('class_record_quarter_id', $quarter->id)
            ->pluck('id');
        abort_if($validDateIds->count() !== $dateIds->count(), 422, 'One or more dates do not belong to this quarter.');

        $studentIds = collect($validated['records'])->pluck('student_id')->unique();
        $validStudentIds = ClassRecordStudent::whereIn('id', $studentIds)
            ->where('class_record_quarter_id', $quarter->id)
            ->pluck('id');
        abort_if($validStudentIds->count() !== $studentIds->count(), 422, 'One or more students do not belong to this class record.');

        $now      = now();
        $toUpsert = [];

        foreach ($validated['records'] as $item) {
            $status = $item['status'] ?? null;

            if ($status === null) {
                DB::table('class_record_ila_records')
                    ->where('class_record_ila_date_id', $item['date_id'])
                    ->where('class_record_student_id', $item['student_id'])
                    ->delete();
            } else {
                $toUpsert[] = [
                    'class_record_ila_date_id' => $item['date_id'],
                    'class_record_student_id'  => $item['student_id'],
                    'status'                   => $status,
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ];
            }
        }

        if ($toUpsert) {
            DB::table('class_record_ila_records')->upsert(
                $toUpsert,
                ['class_record_ila_date_id', 'class_record_student_id'],
                ['status', 'updated_at']
            );
        }

        return response()->json(['message' => count($toUpsert) . ' record(s) saved.']);
    }
}
